<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\I18n\Routes;
use App\Settings\SiteSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan sakila:content-check` — is the content ready to go live?
 *
 * PROBLEMS (exit code 1): placeholder text ("[[TODO…") in public content, demo photos or demo
 * items still present, the "example content" notice still on, photos without an Italian
 * description, a photo the dataset has withdrawn, links that lead nowhere.
 * WORTH IMPROVING (never changes the exit code): search-result descriptions that are missing,
 * repeated, very short or too long.
 * STILL MISSING (exit code 0; 1 with --strict): values the association has not supplied yet
 * (hidden on the public site) and the hand-kept list in database/content/gaps.php.
 */
final class ContentCheck extends Command
{
    protected $signature = 'sakila:content-check {--strict : Also fail while information from the association is missing}';

    protected $description = 'Check that no demo or placeholder content is left and list what the association still has to supply';

    /** table => [parent table, FK column] for translated collections */
    private const COLLECTIONS = [
        'project_translations' => ['projects', 'projectId', 'projects'],
        'news_post_translations' => ['news_posts', 'postId', 'news'],
        'event_translations' => ['events', 'eventId', 'events'],
        'gallery_album_translations' => ['gallery_albums', 'albumId', 'gallery'],
    ];

    /** @var list<string> */
    private array $problems = [];

    /** @var list<string> */
    private array $missing = [];

    /** @var list<string> */
    private array $notes = [];

    public function handle(): int
    {
        $this->placeholdersAndDemoPhotos();
        $this->demoItems();
        $this->settings();
        $this->photosWithoutDescription();
        $this->retiredPhotos();
        $this->links();
        $this->searchDescriptions();

        $gaps = is_file($f = database_path('content/gaps.php')) ? (array) require $f : [];

        if ($this->problems === []) {
            $this->info('✓ No demo or placeholder content, every photo has a description, every link works.');
        } else {
            $this->error(count($this->problems).' problem(s) to fix before going live:');
            foreach ($this->problems as $p) {
                $this->line('  ✗ '.$p);
            }
        }
        if ($this->notes !== []) {
            $this->warn('Worth improving (does not block going live):');
            foreach ($this->notes as $n) {
                $this->line('  • '.$n);
            }
        }
        if ($this->missing !== []) {
            $this->warn('Settings still to fill in (hidden on the public site until then):');
            foreach ($this->missing as $m) {
                $this->line('  • '.$m);
            }
        }
        if ($gaps !== []) {
            $this->warn('Still needed from the association (database/content/gaps.php):');
            foreach ($gaps as $g) {
                $this->line('  • '.$g);
            }
        }

        $failed = $this->problems !== [] || ($this->option('strict') && ($this->missing !== [] || $gaps !== []));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Text columns of everything the public site can show. */
    private function placeholdersAndDemoPhotos(): void
    {
        $sources = [
            'page_sections' => ['content'],
            'page_revisions' => ['snapshot'],
            'page_translations' => ['title', 'seoTitle', 'seoDescription'],
            'project_translations' => ['title', 'summary', 'body', 'sector', 'metaLine'],
            'news_post_translations' => ['title', 'excerpt', 'body'],
            'event_translations' => ['title', 'summary', 'body', 'locationName'],
            'gallery_album_translations' => ['title', 'description'],
            'gallery_items' => ['caption'],
            'report_translations' => ['title', 'description'],
            'navigation_items' => ['label', 'href'],
        ];
        $live = DB::table('page_translations')->whereNotNull('publishedRevisionId')->pluck('publishedRevisionId')->all();
        foreach ($sources as $table => $columns) {
            $query = DB::table($table);
            if ($table === 'page_revisions') {
                $query->whereIn('id', $live); // older revisions are history, not public
            }
            foreach ($query->get() as $row) {
                foreach ($columns as $column) {
                    $value = (string) ($row->{$column} ?? '');
                    if (str_contains($value, '[[TODO')) {
                        preg_match('/\[\[TODO[^\]]*\]\]/', $value, $m);
                        $this->problems[] = "Placeholder text in {$table}.{$column} (id {$row->id}): ".($m[0] ?? '[[TODO…');
                    }
                    if (str_contains($value, 'wikimedia.org/wikipedia') || str_contains($value, 'upload.wikimedia.org')) {
                        $this->problems[] = "Remote Wikimedia image URL in {$table}.{$column} (id {$row->id}).";
                    }
                }
            }
        }
        foreach (['it', 'en'] as $locale) {
            if (str_contains((string) file_get_contents(resource_path("messages/{$locale}.json")), '[[TODO')) {
                $this->problems[] = "Placeholder text in resources/messages/{$locale}.json.";
            }
        }
        foreach (DB::table('media_assets')->get(['id', 'filename', 'storageDriver', 'storageKey', 'url']) as $a) {
            if ($a->storageDriver === 'external' || preg_match('#^https?://#', (string) $a->url) === 1 && str_contains((string) $a->url, 'wikimedia')) {
                $this->problems[] = "Demo photo still in the media library (remote file): {$a->filename}.";
            } elseif (str_starts_with((string) $a->storageKey, 'seed/')) {
                $this->problems[] = "Demo file still in the media library: {$a->filename}.";
            }
        }
    }

    /** Items of the demo seeder (database/seeders/content) that are still in the database. */
    private function demoItems(): void
    {
        foreach (array_keys(self::COLLECTIONS) as $table) {
            $fixture = database_path("seeders/content/{$table}.json");
            if (! is_file($fixture)) {
                continue;
            }
            /** @var list<array<string, mixed>> $rows */
            $rows = json_decode((string) file_get_contents($fixture), true) ?: [];
            $slugs = array_values(array_unique(array_map(fn (array $r): string => (string) ($r['slug'] ?? ''), $rows)));
            foreach (DB::table($table)->whereIn('slug', $slugs)->get(['slug', 'title']) as $hit) {
                $this->problems[] = "Demo item still present in {$table}: “{$hit->title}” ({$hit->slug}).";
            }
        }
    }

    private function settings(): void
    {
        $settings = app(SiteSettings::class)->forEditor();
        foreach ($settings['invalid'] as $column) {
            $this->problems[] = "Settings: the “{$column}” part is invalid and is being ignored.";
        }
        $s = $settings['settings'];
        if (! empty($s['features']['demoContent'])) {
            $this->problems[] = 'The “example content” notice is still switched on (Admin → Settings → Organisation).';
        }
        if (! empty($s['impactStats'])) {
            $this->missing[] = 'Impact numbers are set: make sure every figure is real and documented.';
        }
        $walk = function (mixed $value, string $path) use (&$walk): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $path === '' ? (string) $k : "{$path}.{$k}");
                }
            } elseif (is_string($value) && str_contains($value, '[[TODO')) {
                $this->missing[] = "{$path}: ".trim($value);
            }
        };
        $walk($s, '');
        foreach (['contact.email' => $s['contact']['email'] ?? '', 'organization.taxCode' => $s['organization']['taxCode'] ?? '', 'bankDetails.iban' => $s['bankDetails']['iban'] ?? ''] as $path => $value) {
            if (trim((string) $value) === '') {
                $this->missing[] = "{$path}: empty";
            }
        }
    }

    private function photosWithoutDescription(): void
    {
        foreach (DB::table('media_assets')->where('kind', 'IMAGE')->get(['filename', 'alt']) as $a) {
            $alt = json_decode((string) $a->alt, true);
            if (! is_array($alt) || trim((string) ($alt['it'] ?? '')) === '') {
                $this->problems[] = "Photo without an Italian description: {$a->filename}.";
            }
        }
    }

    /** Photos the dataset no longer publishes (database/content/retired-photos.php) must be gone. */
    private function retiredPhotos(): void
    {
        $file = database_path('content/retired-photos.php');
        $names = is_file($file) ? (array) require $file : [];
        if ($names === []) {
            return;
        }
        foreach (DB::table('media_assets')->whereIn('filename', array_map('strval', $names))->pluck('filename') as $filename) {
            $this->problems[] = "Withdrawn photo still in the media library: {$filename} (delete it in Admin → Photos & files).";
        }
    }

    /**
     * The text shown under the title in search results and link previews: one per public page and
     * language, not repeated, roughly 50–160 characters.
     */
    private function searchDescriptions(): void
    {
        $default = app(SiteSettings::class)->get()['seo']['defaultDescription'] ?? [];
        /** @var array<string, array<string, list<string>>> $seen locale → description → where */
        $seen = ['it' => [], 'en' => []];
        $add = function (string $locale, string $where, string $description) use (&$seen): void {
            $description = trim($description);
            $length = mb_strlen($description);
            if ($description === '') {
                $this->notes[] = "{$where} ({$locale}): no search description, the site-wide one is used.";

                return;
            }
            if ($length < 50) {
                $this->notes[] = "{$where} ({$locale}): search description is very short ({$length} characters).";
            } elseif ($length > 160) {
                $this->notes[] = "{$where} ({$locale}): search description is long ({$length} characters) and will be cut off.";
            }
            $seen[$locale][$description][] = $where;
        };

        foreach (DB::table('page_translations')->whereNotNull('publishedRevisionId')->get(['locale', 'title', 'slug', 'seoDescription']) as $t) {
            if ($t->slug === 'home') {
                $add((string) $t->locale, 'Home', (string) ($t->seoDescription ?: ($default[$t->locale] ?? '')));

                continue;
            }
            $add((string) $t->locale, "Page “{$t->title}”", (string) $t->seoDescription);
        }
        $fallback = ['project_translations' => 'summary', 'news_post_translations' => 'excerpt', 'event_translations' => 'summary'];
        foreach ($fallback as $table => $column) {
            [$parent, $fk] = self::COLLECTIONS[$table];
            $rows = DB::table($table)->join($parent, "{$parent}.id", '=', "{$table}.{$fk}")->where("{$parent}.status", 'PUBLISHED')
                ->get(["{$table}.locale as locale", "{$table}.title as title", "{$table}.seoDescription as seo", "{$table}.{$column} as fallback"]);
            foreach ($rows as $t) {
                $add((string) $t->locale, "“{$t->title}”", (string) ($t->seo ?: $t->fallback));
            }
        }
        foreach ($seen as $locale => $descriptions) {
            foreach ($descriptions as $where) {
                if (count($where) > 1) {
                    $this->notes[] = "Same search description on several pages ({$locale}): ".implode(', ', $where).'.';
                }
            }
        }
    }

    /** Internal links in live pages, content bodies and menus must lead to something published. */
    private function links(): void
    {
        $known = ['it' => [], 'en' => []];
        foreach (DB::table('page_translations')->whereNotNull('publishedRevisionId')->get(['locale', 'slug']) as $t) {
            $known[$t->locale][Routes::page((string) $t->slug)] = true;
        }
        foreach (self::COLLECTIONS as $table => [$parent, $fk, $segment]) {
            $rows = DB::table($table)->join($parent, "{$parent}.id", '=', "{$table}.{$fk}")
                ->where("{$parent}.status", 'PUBLISHED')->get(["{$table}.locale as locale", "{$table}.slug as slug"]);
            foreach ($rows as $t) {
                $known[$t->locale]["/{$segment}/{$t->slug}"] = true;
            }
        }
        $fixed = ['/' => true, '/projects' => true, '/news' => true, '/events' => true, '/gallery' => true, '/donate' => true, '/newsletter' => true];

        $check = function (string $href, string $locale, string $where) use ($known, $fixed): void {
            if ($href === '' || $href[0] !== '/') {
                return; // anchors, https, mailto, tel
            }
            $path = (string) preg_replace('/[#?].*$/', '', $href);
            $path = $path === '' ? '/' : $path;
            if (isset($fixed[$path]) || isset($known[$locale][$path])) {
                return;
            }
            $this->problems[] = "Broken link {$href} ({$locale}) in {$where}.";
        };
        $hrefs = function (mixed $value) use (&$hrefs): array {
            $out = [];
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if ($k === 'href' && is_string($v)) {
                        $out[] = $v;
                    } else {
                        $out = [...$out, ...$hrefs($v)];
                    }
                }
            }

            return $out;
        };

        $live = DB::table('page_translations')->whereNotNull('publishedRevisionId')->pluck('publishedRevisionId')->all();
        foreach (DB::table('page_revisions')->whereIn('id', $live)->get(['id', 'locale', 'snapshot']) as $r) {
            $snapshot = json_decode((string) $r->snapshot, true);
            foreach ($hrefs($snapshot) as $href) {
                $check($href, (string) $r->locale, 'page “'.($snapshot['title'] ?? $r->id).'”');
            }
        }
        foreach (['project_translations', 'news_post_translations', 'event_translations'] as $table) {
            foreach (DB::table($table)->get(['locale', 'title', 'body']) as $t) {
                foreach ($hrefs(json_decode((string) $t->body, true)) as $href) {
                    $check($href, (string) $t->locale, "“{$t->title}”");
                }
            }
        }
        $pages = DB::table('page_translations')->whereNotNull('publishedRevisionId')->pluck('pageId')->flip();
        foreach (DB::table('navigation_items')->get(['label', 'linkType', 'pageId', 'href']) as $item) {
            $label = (string) (json_decode((string) $item->label, true)['it'] ?? '?');
            if ($item->linkType === 'PAGE' && ! isset($pages[$item->pageId])) {
                $this->problems[] = "Menu item “{$label}” points to a page that is not published.";
            }
            if ($item->linkType === 'INTERNAL') {
                foreach (['it', 'en'] as $locale) {
                    $check((string) $item->href, $locale, "menu item “{$label}”");
                }
            }
        }
    }
}
