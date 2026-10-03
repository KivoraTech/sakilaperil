<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Cache\TagCache;
use App\Storage\LocalStorage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Replays the reference app's seeded content (database/seeders/content/*.json, exported once
 * from sakila/prisma/seed.ts) so both apps show identical demo content. Like the reference seed: user references point at the seeded super admin, publish
 * timestamps are "now", event dates are relative to today (in 30 / 60 days), news keep their dates.
 */
class ContentSeeder extends Seeder
{
    public static string $adminId = '';

    /** Insert order (parents first). */
    private const TABLES = [
        'media_assets', 'site_settings',
        'projects', 'project_translations',
        'news_categories', 'news_category_translations', 'news_tags', 'news_tag_translations',
        'news_posts', 'news_post_translations', 'news_post_tags',
        'events', 'event_translations',
        'gallery_albums', 'gallery_album_translations', 'gallery_items',
        'reports', 'report_translations',
        'campaigns', 'campaign_translations',
        'pages', 'page_revisions', 'page_translations', 'page_sections',
        'navigations', 'navigation_items',
    ];

    private const USER_COLUMNS = ['uploadedById', 'createdById', 'updatedById', 'authorId'];

    /** Reference seedEvents(): inDays(d, h). Keyed by the Italian slug. */
    private const EVENT_DATES = [
        'cena-solidale-per-il-nepal' => ['days' => 30, 'start' => 19, 'end' => 22],
        'racconti-dal-nepal' => ['days' => 60, 'start' => 18, 'end' => null],
    ];

    public function run(): void
    {
        if (! is_dir(database_path('seeders/content'))) {
            // The cPanel package ships the real content (SQL file / sakila:load-content), not the demo data.
            throw new RuntimeException('The demo content (database/seeders/content) is not part of this installation.');
        }
        $adminId = self::$adminId !== '' ? self::$adminId : (string) DB::table('users')->where('role', 'SUPER_ADMIN')->value('id');
        if ($adminId === '') {
            throw new RuntimeException('Seed the users first (UsersSeeder).');
        }
        $now = CarbonImmutable::now('UTC');
        $ts = $now->format('Y-m-d H:i:s.v');

        DB::transaction(function () use ($adminId, $now, $ts): void {
            $this->clearContent();
            $eventDates = $this->eventDates($now);
            foreach (self::TABLES as $table) {
                $rows = $this->fixture($table);
                foreach ($rows as $row) {
                    foreach (self::USER_COLUMNS as $col) {
                        if (array_key_exists($col, $row) && $row[$col] !== null) {
                            $row[$col] = $adminId;
                        }
                    }
                    foreach (['createdAt', 'updatedAt'] as $col) {
                        if (array_key_exists($col, $row)) {
                            $row[$col] = $ts;
                        }
                    }
                    if (array_key_exists('publishedAt', $row) && $row['publishedAt'] !== null && $table !== 'news_posts') {
                        $row['publishedAt'] = $ts;
                    }
                    if ($table === 'events' && isset($eventDates[$row['id']])) {
                        [$row['startsAt'], $row['endsAt']] = $eventDates[$row['id']];
                    }
                    DB::table($table)->insert($row);
                }
            }
        });

        // The placeholder annual report PDF (storage key "seed/placeholder-annual-report.pdf").
        $pdf = (string) file_get_contents(database_path('seeders/files/placeholder-annual-report.pdf'));
        (new LocalStorage)->put('seed/placeholder-annual-report.pdf', $pdf, 'application/pdf');

        TagCache::invalidate('settings', 'navigation', 'pages', 'projects', 'news', 'events', 'albums', 'reports', 'media');
        $this->info('Content: 7 pages × 2 locales, 3 projects, 3 news, 2 events, 1 album, 1 report.');
    }

    /** Same deletions as clearContent() in the reference seed (children first). */
    private function clearContent(): void
    {
        foreach ([
            'navigation_items', 'navigations', 'page_translations', 'page_revisions', 'page_sections', 'pages',
            'news_post_tags', 'news_post_translations', 'news_posts', 'news_tag_translations', 'news_tags',
            'news_category_translations', 'news_categories', 'event_translations', 'events',
            'gallery_items', 'gallery_album_translations', 'gallery_albums', 'report_translations', 'reports',
            'campaign_translations', 'campaigns', 'project_translations', 'projects', 'site_settings',
        ] as $table) {
            DB::table($table)->delete();
        }
        DB::table('media_assets')->where('storageDriver', 'external')->delete();
        DB::table('media_assets')->where('storageKey', 'like', 'seed/%')->delete();
    }

    /** @return array<string, array{0: string, 1: ?string}> event id → [startsAt, endsAt] */
    private function eventDates(CarbonImmutable $now): array
    {
        $out = [];
        foreach ($this->fixture('event_translations') as $t) {
            $spec = self::EVENT_DATES[$t['slug']] ?? null;
            if ($t['locale'] !== 'it' || $spec === null) {
                continue;
            }
            $day = $now->addDays($spec['days']);
            $out[$t['eventId']] = [
                $day->setTime($spec['start'], 0)->format('Y-m-d H:i:s.v'),
                $spec['end'] !== null ? $day->setTime($spec['end'], 0)->format('Y-m-d H:i:s.v') : null,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> rows with JSON columns re-encoded (objects stay `{}`) */
    private function fixture(string $table): array
    {
        $json = (string) file_get_contents(database_path("seeders/content/{$table}.json"));
        $rows = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($rows as $row) {
            $r = [];
            foreach (get_object_vars($row) as $col => $value) {
                $r[$col] = is_object($value) && property_exists($value, '__json')
                    ? json_encode($value->__json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
                    : $value;
            }
            $out[] = $r;
        }

        return $out;
    }

    /** Seeders also run from tests/tinker where no console command is attached. */
    private function info(string $message): void
    {
        // $command is a typed property left uninitialised when seeders run outside Artisan.
        if ((new \ReflectionProperty($this, 'command'))->isInitialized($this)) {
            $this->command->info($message);
        }
    }
}
