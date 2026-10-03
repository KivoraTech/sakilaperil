<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Auth\Permissions;
use App\Blocks\BlockRegistry;
use App\Http\Controllers\Admin\PagesActionsController;
use App\I18n\Locales;
use App\Models\GalleryAlbum;
use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\Page;
use App\Models\PagePreviewDraft;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\Project;
use App\Models\User;
use App\Pages\Json;
use App\Pages\Publish;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use stdClass;
use Throwable;

/**
 * Admin reads for the page builder — port of the reference's lib/queries/admin/pages.ts.
 * Every function re-checks the capability itself.
 */
final class PagesQuery
{
    public const PER_PAGE = 20;

    public static function authorize(?User $user, string $capability = 'pages.view'): User
    {
        if (! $user instanceof User || ! Permissions::can($user->role, $capability)) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * listPagesSchema with Zod's `.catch()` semantics: bad values fall back to defaults.
     *
     * @param  array<string, mixed>  $query
     * @return array{q: ?string, status: string, page: int}
     */
    public static function params(array $query): array
    {
        $q = is_string($query['q'] ?? null) ? trim($query['q']) : null;
        if ($q === '' || ($q !== null && mb_strlen($q) > 100)) {
            $q = $q === '' ? '' : null;
        }
        $status = in_array($query['status'] ?? null, ['all', 'published', 'draft', 'changes'], true) ? $query['status'] : 'all';
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = is_int($page) && $page >= 1 && $page <= 10_000 ? $page : 1;

        return ['q' => $q === '' ? null : $q, 'status' => $status, 'page' => $page];
    }

    /**
     * @param  array{q: ?string, status: string, page: int}  $params
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(?User $user, array $params): array
    {
        self::authorize($user);
        $query = Page::query();
        if ($params['q'] !== null) {
            $q = $params['q'];
            $query->whereHas('translations', fn (Builder $t) => $t
                ->where('title', 'like', '%'.self::like($q).'%')
                ->orWhere('slug', 'like', '%'.self::like(mb_strtolower($q)).'%'));
        }
        if ($params['status'] === 'published') {
            $query->where('status', 'PUBLISHED');
        }
        if ($params['status'] === 'draft') {
            $query->where('status', 'DRAFT');
        }
        if ($params['status'] === 'changes') {
            $query->whereHas('translations', fn (Builder $t) => $t->where('hasDraftChanges', true)->whereNotNull('publishedRevisionId'));
        }
        $total = (clone $query)->count();
        $rows = $query
            // key ASC NULLS LAST, then most recently updated
            ->orderByRaw('`key` IS NULL, `key` ASC')
            ->orderByDesc('updatedAt')
            ->skip(($params['page'] - 1) * self::PER_PAGE)
            ->take(self::PER_PAGE)
            ->with(['translations', 'updatedBy:id,name'])
            ->get();

        $items = [];
        foreach ($rows as $p) {
            $states = self::localeStates($p->translations);
            $it = collect($states)->firstWhere('locale', 'it');
            $items[] = [
                'id' => $p->id,
                'key' => $p->key,
                'status' => $p->status->value,
                'title' => $it['title'] ?? ($states[0]['title'] ?? '(untitled)'),
                'locales' => $states,
                'updatedAt' => Json::iso($p->updatedAt),
                'updatedBy' => $p->updatedBy?->name,
            ];
        }

        return ['items' => $items, 'total' => $total, 'page' => $params['page'], 'pageCount' => max(1, (int) ceil($total / self::PER_PAGE))];
    }

    /**
     * @param  Collection<int, PageTranslation>  $translations
     * @return list<array{locale: string, title: string, slug: string, isPublished: bool, hasDraftChanges: bool, publishedAt: ?string}>
     */
    public static function localeStates(Collection $translations): array
    {
        $out = [];
        foreach (Locales::ALL as $l) {
            $t = $translations->first(fn (PageTranslation $t) => $t->locale->value === $l);
            if ($t === null) {
                continue;
            }
            $out[] = [
                'locale' => $l,
                'title' => $t->title,
                'slug' => $t->slug,
                'isPublished' => $t->publishedRevisionId !== null,
                'hasDraftChanges' => (bool) $t->hasDraftChanges,
                'publishedAt' => Json::iso($t->publishedAt),
            ];
        }

        return $out;
    }

    /** @return array<string, mixed>|null PageEditorData */
    public static function editor(?User $user, string $pageId, string $locale): ?array
    {
        self::authorize($user);
        $page = Page::query()->with('translations')->withCount('navigationItems')->find($pageId);
        if ($page === null) {
            return null;
        }
        $translations = self::localeStates($page->translations);
        /** @var PageTranslation|null $t */
        $t = $page->translations->first(fn (PageTranslation $x) => $x->locale->value === $locale);
        $draft = null;
        if ($t !== null) {
            $rows = PageSection::query()->where('pageId', $pageId)->where('locale', $locale)->orderBy('position')->get();
            $revisions = PageRevision::query()->where('pageId', $pageId)->where('locale', $locale)
                ->orderByDesc('createdAt')->take(10)->with('createdBy:id,name')->get(['id', 'createdAt', 'createdById']);
            $sections = [];
            $issueRows = [];
            foreach ($rows as $r) {
                $row = PagesActionsController::sectionRow($r);
                $issueRows[] = $row;
                $sections[] = self::editorSection($row);
            }
            $draft = [
                'title' => $t->title,
                'slug' => $t->slug,
                'seoTitle' => $t->seoTitle ?? '',
                'seoDescription' => $t->seoDescription ?? '',
                'ogImageId' => $t->ogImageId ?? '',
                'noIndex' => (bool) $t->noIndex,
                'isPublished' => $t->publishedRevisionId !== null,
                'hasDraftChanges' => (bool) $t->hasDraftChanges,
                'publishedAt' => Json::iso($t->publishedAt),
                'version' => Json::iso($t->updatedAt),
                'updatedAt' => Json::iso($t->updatedAt),
                'sections' => $sections,
                'issues' => Publish::validateDraftSections($issueRows),
                'revisions' => $revisions->map(fn (PageRevision $r) => [
                    'id' => $r->id,
                    'createdAt' => Json::iso($r->createdAt),
                    'createdBy' => $r->createdBy?->name,
                    'isLive' => $r->id === $t->publishedRevisionId,
                ])->values()->all(),
            ];
        }

        return [
            'page' => [
                'id' => $page->id,
                'key' => $page->key,
                'status' => $page->status->value,
                'menuReferences' => (int) $page->getAttribute('navigation_items_count'),
            ],
            'locale' => $locale,
            'translations' => $translations,
            'draft' => $draft,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed> EditorSection
     */
    private static function editorSection(array $row): array
    {
        $base = [
            'id' => $row['id'],
            'type' => $row['type'],
            'isVisible' => $row['isVisible'],
            'content' => Json::object($row['content']),
            'settings' => Json::object($row['settings']),
            'migrated' => false,
            'broken' => false,
        ];
        if (! BlockRegistry::isBlockType($row['type'])) {
            return [...$base, 'broken' => true];
        }
        try {
            $m = BlockRegistry::migrate($row['type'], (int) $row['schemaVersion'], ['content' => $row['content'], 'settings' => $row['settings']]);
        } catch (Throwable) {
            return [...$base, 'broken' => true];
        }

        return [
            ...$base,
            'content' => Json::object($m['content']),
            'settings' => Json::object($m['settings']),
            'migrated' => $m['content'] !== $row['content'],
        ];
    }

    /**
     * Options for the block editor's pickers (projects, albums, categories). Images are searched
     * server-side by the media picker; the 300 most recent are preloaded like the reference.
     *
     * @return array<string, list<array<string, mixed>>> BuilderReferences
     */
    public static function references(?User $user, string $locale): array
    {
        self::authorize($user);
        $images = MediaAsset::query()->where('kind', 'IMAGE')->orderByDesc('createdAt')->take(300)
            ->get(['id', 'url', 'filename', 'alt', 'width', 'height']);
        $projects = Project::query()->orderBy('position')->with('translations:id,projectId,locale,title')->get(['id', 'status']);
        $albums = GalleryAlbum::query()->orderBy('position')->with('translations:id,albumId,locale,title')->get(['id', 'status']);
        $categories = NewsCategory::query()->orderBy('position')->with('translations:id,categoryId,locale,name')->get(['id', 'key']);

        $titleOf = function (Collection $ts, string $field = 'title') use ($locale): ?string {
            $match = $ts->first(fn ($t) => $t->locale->value === $locale)
                ?? $ts->first(fn ($t) => $t->locale->value === 'it')
                ?? $ts->first();

            return $match?->{$field};
        };

        return [
            'images' => $images->map(fn (MediaAsset $i) => [
                'id' => $i->id,
                'url' => $i->url,
                'filename' => $i->filename,
                'alt' => Locales::pick(is_array($i->alt) ? $i->alt : null, $locale),
                'width' => $i->width,
                'height' => $i->height,
            ])->values()->all(),
            'projects' => $projects->map(fn ($p) => ['id' => $p->id, 'label' => $titleOf($p->translations) ?? '—', 'status' => $p->status->value])->values()->all(),
            'albums' => $albums->map(fn ($a) => ['id' => $a->id, 'label' => $titleOf($a->translations) ?? '—', 'status' => $a->status->value])->values()->all(),
            'newsCategories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'label' => data_get($c->translations->first(fn ($t) => $t->locale->value === $locale) ?? $c->translations->first(), 'name') ?? $c->key,
            ])->values()->all(),
        ];
    }

    /**
     * Internal link suggestions for link fields (locale-less paths, ADR-007).
     *
     * @return list<array{href: string, label: string}>
     */
    public static function linkSuggestions(?User $user, string $locale): array
    {
        self::authorize($user);
        $pages = PageTranslation::query()->where('locale', $locale)->orderBy('title')->get(['title', 'slug']);

        return [
            ...$pages->map(fn ($p) => ['href' => $p->slug === 'home' ? '/' : '/'.$p->slug, 'label' => $p->title])->values()->all(),
            ['href' => '/projects', 'label' => 'Projects'],
            ['href' => '/news', 'label' => 'News & stories'],
            ['href' => '/events', 'label' => 'Events'],
            ['href' => '/gallery', 'label' => 'Gallery'],
            ['href' => '#dona', 'label' => 'Donate section on this page'],
        ];
    }

    /**
     * Draft sections + meta for the preview route.
     *
     * @return array{title: string, slug: string, publishedRevisionId: ?string, hasDraftChanges: bool, pageKey: ?string, updatedAt: ?string, sections: list<array<string, mixed>>}|null
     */
    public static function draftForPreview(?User $user, string $pageId, string $locale): ?array
    {
        self::authorize($user);
        $t = PageTranslation::query()->with('page:id,key')->where('pageId', $pageId)->where('locale', $locale)->first();
        if ($t === null) {
            return null;
        }
        $sections = PageSection::query()->where('pageId', $pageId)->where('locale', $locale)->orderBy('position')->get()
            ->map(fn (PageSection $s) => PagesActionsController::sectionRow($s))->values()->all();

        return [
            'title' => $t->title,
            'slug' => $t->slug,
            'publishedRevisionId' => $t->publishedRevisionId,
            'hasDraftChanges' => (bool) $t->hasDraftChanges,
            'pageKey' => $t->page?->key,
            'updatedAt' => Json::iso($t->updatedAt),
            'sections' => $sections,
        ];
    }

    /**
     * The current user's staged (unsaved) editor state for the live preview, or null. Only a row
     * staged by the same editor tab (`clientId`) and younger than the TTL is used.
     *
     * @return array{title: string, sections: list<array<string, mixed>>, updatedAt: ?string}|null
     */
    public static function stagedPreview(?User $user, string $pageId, string $locale, string $clientId): ?array
    {
        $user = self::authorize($user);
        if (preg_match('/^[A-Za-z0-9_-]{8,64}$/', $clientId) !== 1) {
            return null;
        }
        $row = PagePreviewDraft::query()->where('userId', $user->id)->where('pageId', $pageId)->where('locale', $locale)->first();
        if ($row === null || $row->clientId !== $clientId) {
            return null;
        }
        if ($row->updatedAt->lt(now()->subSeconds(PagesActionsController::PREVIEW_DRAFT_TTL_SECONDS))) {
            return null;
        }
        $sections = Json::column($row, 'sections');
        $list = [];
        foreach (is_array($sections) ? $sections : [] as $position => $s) {
            if (! $s instanceof stdClass) {
                continue;
            }
            $list[] = [
                'id' => (string) ($s->id ?? ''),
                'type' => (string) ($s->type ?? ''),
                'schemaVersion' => (int) ($s->schemaVersion ?? 1),
                'isVisible' => (bool) ($s->isVisible ?? true),
                'position' => $position,
                'content' => $s->content ?? null,
                'settings' => $s->settings ?? new stdClass,
            ];
        }

        return ['title' => $row->title, 'sections' => $list, 'updatedAt' => Json::iso($row->updatedAt)];
    }

    private static function like(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
