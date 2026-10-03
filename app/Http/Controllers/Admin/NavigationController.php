<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\I18n\Routes;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\User;
use App\Pages\Input;
use App\Pages\Json;
use App\Pages\PageCache;
use App\Pages\RichTextSanitizer;
use App\Queries\Admin\PagesQuery;
use App\Support\Cuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Menus editor + save action (reference app/admin/(shell)/navigation + lib/actions/navigation.ts). */
final class NavigationController extends Controller
{
    public const KEYS = ['header', 'footer-explore', 'footer-help', 'footer-legal'];

    /** Same as navigationLabels in resources/js/lib/navigation/schema.ts. */
    public const LABELS = [
        'header' => ['name' => 'Header menu', 'nested' => true],
        'footer-explore' => ['name' => 'Footer · Explore', 'nested' => false],
        'footer-help' => ['name' => 'Footer · Get involved', 'nested' => false],
        'footer-legal' => ['name' => 'Footer · Legal', 'nested' => false],
    ];

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        PagesQuery::authorize($user, 'navigation.manage');

        return Inertia::render('admin/navigation/index', [
            'menu' => is_string($request->query('menu')) ? (string) $request->query('menu') : 'header',
            'data' => self::editorData(),
        ]);
    }

    /** @return array{menus: list<array<string, mixed>>, pages: list<array<string, mixed>>} */
    public static function editorData(): array
    {
        $navs = Navigation::query()->with(['items' => fn ($q) => $q->orderBy('position')])->get();
        $pages = Page::query()->orderBy('createdAt')->with('translations:id,pageId,locale,title,publishedRevisionId')->get(['id', 'status', 'createdAt']);

        $asLabel = function (mixed $v): array {
            $o = is_array($v) ? $v : [];
            $label = ['it' => is_string($o['it'] ?? null) ? $o['it'] : ''];
            if (is_string($o['en'] ?? null)) {
                $label['en'] = $o['en'];
            }

            return $label;
        };
        $toItem = fn (NavigationItem $i, int $depth): array => array_filter([
            'key' => $i->id,
            'label' => $asLabel($i->label),
            'linkType' => $i->linkType->value,
            'pageId' => $i->pageId,
            'routeKey' => $i->routeKey,
            'href' => $i->href,
            'openInNewTab' => (bool) $i->openInNewTab,
            'isVisible' => (bool) $i->isVisible,
            'depth' => $depth,
        ], fn ($v) => $v !== null);

        $menus = [];
        foreach (self::KEYS as $key) {
            $nav = $navs->firstWhere('key', $key);
            $items = [];
            if ($nav !== null) {
                foreach ($nav->items->whereNull('parentId') as $top) {
                    $items[] = $toItem($top, 0);
                    foreach ($nav->items->where('parentId', $top->id) as $child) {
                        $items[] = $toItem($child, 1);
                    }
                }
            }
            $menus[] = ['key' => $key, 'items' => $items, 'updatedAt' => $nav !== null ? Json::iso($nav->updatedAt) : null];
        }

        return [
            'menus' => $menus,
            'pages' => $pages->map(function (Page $p) {
                $it = $p->translations->first(fn ($t) => $t->locale->value === 'it') ?? $p->translations->first();

                return [
                    'id' => $p->id,
                    'title' => data_get($it, 'title') ?? '(untitled)',
                    'isPublished' => $p->status->value === 'PUBLISHED' && $p->translations->contains(fn ($t) => $t->publishedRevisionId !== null),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Replace all items of one menu (ordered, max one nesting level). Navigation has no draft
     * state: saving goes live immediately, so the cache is expired.
     */
    public function save(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            /** @var User $user */
            $user = $request->user();
            $data = Input::schema('navigation', 'saveNavigationSchema', Input::body($request));
            $key = (string) $data->key;
            /** @var list<\stdClass> $items */
            $items = array_values((array) $data->items);

            $errors = [];
            foreach ($items as $i => $item) {
                $label = $item->label ?? null;
                $it = is_object($label) ? Input::text($label->it ?? '') : '';
                if ($it === '') {
                    $errors['items'][] = 'Enter the Italian label';
                }
                $href = isset($item->href) ? Input::text($item->href) : null;
                $problem = match ($item->linkType) {
                    'PAGE' => empty($item->pageId) ? 'Choose a page' : null,
                    'ROUTE' => ! in_array($item->routeKey ?? null, Routes::routeKeys(), true) ? 'Choose a section of the site' : null,
                    'INTERNAL' => ($href === null || preg_match('~^[/#]~', $href) !== 1 || ! RichTextSanitizer::isSafeHref($href))
                        ? 'Use a site path without the language, e.g. /projects or /chi-siamo#team' : null,
                    'EXTERNAL' => ($href === null || ! str_starts_with($href, 'https://') || ! RichTextSanitizer::isSafeHref($href))
                        ? 'Use a full https:// address' : null,
                    default => 'Invalid option',
                };
                if ($problem !== null) {
                    $errors['items'][] = $problem;
                }
                if ($i === 0 && (int) ($item->depth ?? 0) !== 0) {
                    $errors['items'][] = 'The first link cannot be nested';
                }
                if (! self::LABELS[$key]['nested'] && (int) ($item->depth ?? 0) !== 0) {
                    $errors['items'][] = 'This menu does not support nesting';
                }
            }
            if ($errors !== []) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', $errors);
            }

            $pageIds = array_values(array_unique(array_map(
                fn ($i) => (string) $i->pageId,
                array_filter($items, fn ($i) => $i->linkType === 'PAGE'),
            )));
            if ($pageIds !== [] && Page::query()->whereIn('id', $pageIds)->count() !== count($pageIds)) {
                return ActionResult::fail('VALIDATION', 'One of the linked pages no longer exists. Reload and try again.');
            }

            DB::transaction(function () use ($key, $items): void {
                $nav = Navigation::query()->firstOrCreate(['key' => $key], ['name' => self::LABELS[$key]['name']]);
                $nav->touch();
                NavigationItem::query()->where('navigationId', $nav->id)->update(['parentId' => null]);
                NavigationItem::query()->where('navigationId', $nav->id)->delete();
                $parentId = null;
                $position = 0;
                $childPosition = 0;
                foreach ($items as $item) {
                    $label = ['it' => Input::text($item->label->it)];
                    $en = Input::text($item->label->en ?? '');
                    if ($en !== '') {
                        $label['en'] = $en;
                    }
                    $values = [
                        'navigationId' => $nav->id,
                        'label' => $label,
                        'linkType' => $item->linkType,
                        'pageId' => $item->linkType === 'PAGE' ? (string) $item->pageId : null,
                        'routeKey' => $item->linkType === 'ROUTE' ? (string) $item->routeKey : null,
                        'href' => in_array($item->linkType, ['INTERNAL', 'EXTERNAL'], true) ? Input::text($item->href) : null,
                        'openInNewTab' => (bool) ($item->openInNewTab ?? false),
                        'isVisible' => (bool) ($item->isVisible ?? true),
                    ];
                    if ((int) ($item->depth ?? 0) === 1 && $parentId !== null) {
                        NavigationItem::query()->create([...$values, 'parentId' => $parentId, 'position' => $childPosition++]);
                    } else {
                        $created = NavigationItem::query()->create([...$values, 'id' => Cuid::make(), 'parentId' => null, 'position' => $position++]);
                        $parentId = $created->id;
                        $childPosition = 0;
                    }
                }
            });

            Audit::log(AuditAction::NAVIGATION_UPDATED, $user->id, 'Navigation', $key, ['items' => count($items)]);
            PageCache::expireNavigation();

            return ActionResult::ok(['count' => count($items)], 'Menu saved — it is live on the website');
        });
    }
}
