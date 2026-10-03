<?php

declare(strict_types=1);

namespace App\Content\Real;

use App\Cache\TagCache;
use App\Content\Uploads;
use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Http\Controllers\Admin\EventsController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\PagesActionsController;
use App\Http\Controllers\Admin\ProjectsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Storage\Storage;
use App\Support\Cuid;
use App\Validation\JsonSchemas;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Loads the association's REAL content (database/content/*.php + photos) through the CMS's own
 * actions: the same controllers the admin screens call, so every item is validated, sanitised,
 * audited and published exactly as if an administrator had typed it in. Photos go through the
 * upload pipeline (orientation fixed, EXIF/GPS removed, size capped).
 *
 * The demo seeder (database/seeders) is separate and unchanged; it is only for tests and demos.
 * Never touches users, form submissions, donations, newsletter subscribers or the audit history.
 */
final class ContentLoader
{
    /** Children first. Same list as the demo seeder's clearContent(). */
    private const CONTENT_TABLES = [
        'navigation_items', 'navigations', 'page_translations', 'page_revisions', 'page_sections', 'pages',
        'news_post_tags', 'news_post_translations', 'news_posts', 'news_tag_translations', 'news_tags',
        'news_category_translations', 'news_categories', 'event_translations', 'events',
        'gallery_items', 'gallery_album_translations', 'gallery_albums', 'report_translations', 'reports',
        'campaign_translations', 'campaigns', 'project_translations', 'projects', 'site_settings',
    ];

    /** Tables that mean "this site already has content" (settings alone do not). */
    private const PROBE_TABLES = ['pages', 'projects', 'news_posts', 'events', 'gallery_albums', 'navigations'];

    private const SETTINGS_SECTIONS = ['organization', 'contact', 'donations', 'impact', 'seo', 'appearance', 'messages'];

    private const MENUS = ['header', 'footer-explore', 'footer-help', 'footer-legal'];

    /** @var array<string, array<string, string>> kind → dataset key → database id */
    private array $ids = ['media' => [], 'project' => [], 'news' => [], 'event' => [], 'album' => [], 'page' => []];

    private User $actor;

    private bool $temporaryActor = false;

    /** @param Closure(string): void $log */
    public function __construct(private readonly string $dir, private readonly Closure $log) {}

    public static function hasContent(): bool
    {
        foreach (self::PROBE_TABLES as $table) {
            if (DB::table($table)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, int> what was loaded */
    public function load(bool $fresh): array
    {
        $this->actor = $this->resolveActor();
        Auth::setUser($this->actor);
        try {
            if ($fresh) {
                $this->wipe();
            }
            $this->ensureSettingsRow();
            $counts = [
                'photos' => $this->media(),
                'settings sections' => $this->settings(),
                'projects' => $this->collection('projects', 'project', ProjectsController::class),
                'news' => $this->collection('news', 'news', NewsController::class),
                'events' => $this->collection('events', 'event', EventsController::class),
                'albums' => $this->collection('albums', 'album', GalleryController::class),
                'pages' => $this->pages(),
                'menus' => $this->menus(),
            ];
            TagCache::invalidate('settings', 'navigation', 'pages', 'projects', 'news', 'events', 'albums', 'reports', 'media');

            return $counts;
        } finally {
            if ($this->temporaryActor) {
                // Authorship columns are ON DELETE SET NULL: content stays, the helper account goes.
                $this->actor->delete();
            }
        }
    }

    // ── Actor ───────────────────────────────────────────────────────────

    /** The first active super administrator, or a temporary one on an install without users. */
    private function resolveActor(): User
    {
        $admin = User::query()->where('role', Role::SUPER_ADMIN)->where('isActive', true)->orderBy('createdAt')->first();
        if ($admin instanceof User) {
            return $admin;
        }
        $this->temporaryActor = true;

        return User::query()->create([
            'id' => Cuid::make(),
            'name' => 'Content loader',
            'email' => 'content-loader-'.bin2hex(random_bytes(6)).'@invalid.example',
            'emailVerified' => false,
            'role' => Role::SUPER_ADMIN,
            'isActive' => false, // cannot sign in; has no password account either
        ])->refresh();
    }

    // ── Wipe ────────────────────────────────────────────────────────────

    private function wipe(): void
    {
        DB::transaction(function (): void {
            foreach (self::CONTENT_TABLES as $table) {
                DB::table($table)->delete();
            }
        });
        // Demo photos (remote URLs / seed files) and photos loaded earlier from this dataset.
        // Files uploaded by people in the admin are kept.
        $names = [
            ...array_map(fn (array $m): string => (string) $m['file'], $this->data('media')),
            ...$this->retiredPhotos(),
        ];
        $assets = MediaAsset::query()
            ->where('storageDriver', 'external')
            ->orWhere('storageKey', 'like', 'seed/%')
            ->orWhereIn('filename', $names)
            ->get();
        foreach ($assets as $asset) {
            $driver = (string) $asset->storageDriver;
            if ($driver !== 'external' && ! str_starts_with((string) $asset->storageKey, 'seed/')) {
                try {
                    Storage::for($driver)?->delete((string) $asset->storageKey);
                } catch (Throwable) {
                    // A missing file must not stop the reload.
                }
            }
            $asset->delete();
        }
        ($this->log)('Removed existing pages, projects, news, events, albums, menus, settings and dataset/demo photos.');
    }

    /**
     * Photos an earlier version of the dataset loaded and no longer publishes (retired-photos.php).
     *
     * @return list<string>
     */
    private function retiredPhotos(): array
    {
        $file = "{$this->dir}/retired-photos.php";
        $names = is_file($file) ? require $file : [];

        return is_array($names) ? array_values(array_map('strval', $names)) : [];
    }

    private function ensureSettingsRow(): void
    {
        if (DB::table('site_settings')->where('id', 'default')->exists()) {
            return;
        }
        $defaults = SiteSettings::defaults();
        $now = now('UTC')->format('Y-m-d H:i:s.v');
        $row = ['id' => 'default', 'orgName' => (string) $defaults['orgName'], 'createdAt' => $now, 'updatedAt' => $now];
        foreach (array_keys(SiteSettings::COLUMNS) as $column) {
            $value = $defaults[$column];
            $row[$column] = JsonSchemas::encode(is_array($value) && $value === [] && ! in_array($column, ['socialLinks', 'impactStats'], true) ? new \stdClass : $value);
        }
        DB::table('site_settings')->insert($row);
    }

    // ── Media ───────────────────────────────────────────────────────────

    private function media(): int
    {
        $n = 0;
        foreach ($this->data('media') as $m) {
            $key = (string) $m['key'];
            $file = (string) $m['file'];
            $path = $this->dir.'/photos/'.$file;
            $existing = MediaAsset::query()->where('filename', $file)->first();
            if ($existing === null) {
                if (! is_file($path)) {
                    throw new RuntimeException("Photo not found: {$path}");
                }
                $stored = Uploads::store((string) file_get_contents($path), $file, ['IMAGE']);
                if (! $stored['ok']) {
                    throw new RuntimeException("Photo {$file}: {$stored['message']}");
                }
                $d = $stored['data'];
                $existing = MediaAsset::query()->create([
                    'kind' => $d['kind'],
                    'storageDriver' => $d['storageDriver'],
                    'storageKey' => $d['storageKey'],
                    'url' => $d['url'],
                    'filename' => $d['filename'],
                    'mimeType' => $d['mimeType'],
                    'size' => $d['size'],
                    'width' => $d['width'],
                    'height' => $d['height'],
                    'alt' => ['it' => (string) $m['alt']['it']],
                    'uploadedById' => $this->actor->id,
                ]);
            }
            $this->act(MediaController::class, 'update', [
                'id' => (string) $existing->id,
                'input' => [
                    'altIt' => (string) $m['alt']['it'],
                    'altEn' => (string) ($m['alt']['en'] ?? ''),
                    'captionIt' => (string) ($m['caption']['it'] ?? ''),
                    'captionEn' => (string) ($m['caption']['en'] ?? ''),
                    'credit' => (string) ($m['credit'] ?? ''),
                    'focalX' => $m['focalX'] ?? null,
                    'focalY' => $m['focalY'] ?? null,
                ],
            ], "photo {$file}");
            $this->ids['media'][$key] = (string) $existing->id;
            $n++;
        }

        return $n;
    }

    // ── Settings ────────────────────────────────────────────────────────

    private function settings(): int
    {
        $settings = $this->resolve($this->data('settings'));
        $n = 0;
        foreach (self::SETTINGS_SECTIONS as $section) {
            if (! isset($settings[$section])) {
                continue;
            }
            $this->act(SettingsController::class, 'update', ['section' => $section, 'data' => $settings[$section]], "settings: {$section}");
            $n++;
        }

        return $n;
    }

    // ── Projects, news, events, albums ──────────────────────────────────

    /** @param class-string $controller */
    private function collection(string $file, string $kind, string $controller): int
    {
        $n = 0;
        foreach ($this->data($file) as $item) {
            $key = (string) $item['key'];
            unset($item['key']);
            $data = $this->act($controller, 'save', ['id' => null, 'input' => $this->resolve($item), 'intent' => 'publish'], "{$kind} {$key}");
            $this->ids[$kind][$key] = (string) $data['id'];
            if ($kind === 'news' && ($item['authorId'] ?? null) === null) {
                // The save action signs a story with whoever saves it. Dataset stories are the
                // association's own announcements: no personal byline (and never the loader's account).
                DB::table('news_posts')->where('id', (string) $data['id'])->update(['authorId' => null]);
            }
            $n++;
        }

        return $n;
    }

    // ── Pages ───────────────────────────────────────────────────────────

    private function pages(): int
    {
        $pages = $this->data('pages');
        // First create every page (so pages can link to each other by reference), then fill and publish.
        foreach ($pages as $p) {
            $ref = (string) $p['ref'];
            $key = $p['key'] ?? null;
            if ($key !== null) {
                // System pages (home, about, contact…) carry a fixed key the site's routes rely on.
                $page = DB::transaction(function () use ($p, $key): Page {
                    $page = Page::query()->create([
                        'key' => $key,
                        'status' => ContentStatus::DRAFT,
                        'createdById' => $this->actor->id,
                        'updatedById' => $this->actor->id,
                    ]);
                    foreach (['it', 'en'] as $locale) {
                        PageTranslation::query()->create([
                            'pageId' => $page->id, 'locale' => $locale,
                            'title' => (string) $p[$locale]['title'], 'slug' => (string) $p[$locale]['slug'],
                        ]);
                    }

                    return $page;
                });
                $this->ids['page'][$ref] = (string) $page->id;
            } else {
                $data = $this->act(PagesActionsController::class, 'create', [
                    'title' => $p['it']['title'], 'slug' => $p['it']['slug'],
                    'enTitle' => $p['en']['title'], 'enSlug' => $p['en']['slug'],
                ], "page {$ref}");
                $this->ids['page'][$ref] = (string) $data['id'];
            }
        }
        foreach ($pages as $p) {
            $ref = (string) $p['ref'];
            $pageId = $this->ids['page'][$ref];
            foreach (['it', 'en'] as $locale) {
                $t = $p[$locale];
                $sections = [];
                foreach ($t['sections'] as $s) {
                    $sections[] = [
                        'type' => (string) $s['type'],
                        'isVisible' => true,
                        'content' => $this->objects($this->resolve($s['content'] ?? [])),
                        'settings' => $this->objects($s['settings'] ?? []),
                    ];
                }
                $saved = $this->act(PagesActionsController::class, 'saveDraft', [
                    'pageId' => $pageId,
                    'locale' => $locale,
                    'sections' => $sections,
                    'meta' => [
                        'pageId' => $pageId, 'locale' => $locale,
                        'title' => $t['title'], 'slug' => $t['slug'],
                        'seoTitle' => $t['seoTitle'] ?? '',
                        'seoDescription' => $t['seoDescription'] ?? '',
                        'ogImageId' => $this->resolve($t['ogImage'] ?? ''),
                        'noIndex' => (bool) ($t['noIndex'] ?? false),
                    ],
                ], "page {$ref} ({$locale}) draft");
                if (($saved['issues'] ?? []) !== []) {
                    throw new RuntimeException("Page {$ref} ({$locale}) has invalid sections: ".json_encode($saved['issues'], JSON_UNESCAPED_UNICODE));
                }
                $this->act(PagesActionsController::class, 'publish', ['pageId' => $pageId, 'locale' => $locale], "page {$ref} ({$locale}) publish");
            }
        }

        return count($pages);
    }

    // ── Menus ───────────────────────────────────────────────────────────

    private function menus(): int
    {
        $menus = $this->resolve($this->data('menus'));
        foreach (self::MENUS as $key) {
            $this->act(NavigationController::class, 'save', ['key' => $key, 'items' => $menus[$key] ?? []], "menu {$key}");
        }

        return count(self::MENUS);
    }

    // ── Plumbing ────────────────────────────────────────────────────────

    /** @return array<int|string, mixed> */
    private function data(string $name): array
    {
        $file = "{$this->dir}/{$name}.php";
        if (! is_file($file)) {
            throw new RuntimeException("Content file missing: {$file}");
        }
        $data = require $file;
        if (! is_array($data)) {
            throw new RuntimeException("Content file must return an array: {$file}");
        }

        return $data;
    }

    /**
     * Replace "@media:key", "@project:key", "@news:key", "@event:key", "@album:key", "@page:key"
     * strings with the database id of the item loaded earlier.
     */
    private function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($v) => $this->resolve($v), $value);
        }
        if (is_string($value) && preg_match('/^@(media|project|news|event|album|page):([a-z0-9-]+)$/', $value, $m) === 1) {
            return $this->ids[$m[1]][$m[2]] ?? throw new RuntimeException("Unknown content reference {$value}");
        }

        return $value;
    }

    /** JSON objects must stay objects: an empty PHP array would be sent as `[]`. */
    private function objects(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return new \stdClass;
        }
        if (array_is_list($value)) {
            return array_map(fn ($v) => is_array($v) && $v !== [] ? $this->objects($v) : $v, $value);
        }
        $out = [];
        foreach ($value as $k => $v) {
            // Keys that hold lists by schema stay lists even when empty.
            $out[$k] = is_array($v) ? ($v === [] ? [] : $this->objects($v)) : $v;
        }

        return $out;
    }

    /**
     * Call an admin action as the acting administrator and return its `data`.
     *
     * @param  class-string  $controller
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function act(string $controller, string $method, array $payload, string $what): array
    {
        $request = Request::create('/admin/actions/content-loader', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $request->setUserResolver(fn (): User => $this->actor);
        app()->instance('request', $request);

        /** @var JsonResponse $response */
        $response = app($controller)->{$method}($request);
        /** @var array<string, mixed> $json */
        $json = $response->getData(true);
        if (($json['ok'] ?? false) !== true) {
            $detail = isset($json['fieldErrors']) ? ' '.json_encode($json['fieldErrors'], JSON_UNESCAPED_UNICODE) : '';
            throw new RuntimeException("Could not save {$what}: ".($json['message'] ?? 'unknown error').$detail
                .(($json['code'] ?? '') === 'ERROR' ? ' (details in storage/logs)' : ''));
        }
        ($this->log)("✓ {$what}");
        $data = $json['data'] ?? [];

        return is_array($data) ? $data : [];
    }
}
