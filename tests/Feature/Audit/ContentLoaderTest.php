<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Storage\Storage;
use App\Support\Cuid;
use Illuminate\Support\Facades\DB;

/*
| The REAL content that ships (database/content) and its two commands:
|   php artisan sakila:load-content [--fresh]     php artisan sakila:content-check [--strict]
| The dataset is loaded once per test (it goes through the real upload pipeline, so it is not free);
| each test therefore checks one whole scenario. Independent of the demo seeder.
*/

beforeEach(function () {
    $this->uploads = sys_get_temp_dir().'/sakila-real-content-'.bin2hex(random_bytes(4));
    config(['sakila.local_uploads_dir' => $this->uploads, 'sakila.storage_driver' => 'local']);
    Storage::reset();
});

afterEach(function () {
    if (is_dir($this->uploads)) {
        exec('rm -rf '.escapeshellarg($this->uploads));
    }
    Storage::reset();
});

/** Row counts of every content table plus the things a reload must never touch. */
function contentCounts(): array
{
    $tables = [
        'pages', 'page_translations', 'page_sections', 'projects', 'project_translations', 'news_posts',
        'news_post_translations', 'events', 'event_translations', 'gallery_albums', 'gallery_items',
        'navigations', 'navigation_items', 'media_assets', 'site_settings', 'users', 'contact_submissions',
        'newsletter_subscribers',
    ];
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

function storedFiles(string $dir): int
{
    if (! is_dir($dir)) {
        return 0;
    }
    $n = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $n += $file->isFile() ? 1 : 0;
    }

    return $n;
}

it('loads the real dataset, refuses to load twice, and --fresh reloads it identically without touching people\'s data', function () {
    $admin = UserAccounts::create('Ada Admin', 'ada@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $now = now('UTC')->format('Y-m-d H:i:s.v');
    DB::table('contact_submissions')->insert([
        'id' => Cuid::make(), 'name' => 'Visitor', 'email' => 'visitor@example.org', 'message' => 'Hello',
        'consentAt' => $now, 'createdAt' => $now, 'updatedAt' => $now,
    ]);
    DB::table('newsletter_subscribers')->insert([
        'id' => Cuid::make(), 'email' => 'reader@example.org', 'status' => 'SUBSCRIBED',
        'consentAt' => $now, 'createdAt' => $now, 'updatedAt' => $now,
    ]);

    // 1. First load into an empty site.
    $this->artisan('sakila:load-content')->assertSuccessful();
    $first = contentCounts();
    $files = storedFiles($this->uploads);
    expect($first['pages'])->toBe(8)
        ->and($first['projects'])->toBe(3)
        ->and($first['news_posts'])->toBe(2)
        ->and($first['events'])->toBe(1)
        ->and($first['gallery_albums'])->toBe(3)
        ->and($first['navigations'])->toBe(4)
        ->and($first['users'])->toBe(1)                 // no helper account is left behind
        ->and($first['contact_submissions'])->toBe(1)
        ->and($first['newsletter_subscribers'])->toBe(1)
        ->and($files)->toBeGreaterThan(0);

    // News: fixed dates from the dataset (never "the day it was loaded") and no personal byline.
    $news = DB::table('news_posts')->orderBy('publishedAt')->get(['publishedAt', 'authorId', 'status']);
    expect($news->pluck('publishedAt')->map(fn ($d) => substr((string) $d, 0, 16))->all())
        ->toBe(['2026-05-16 19:00', '2026-08-26 10:00'])       // stored in UTC
        ->and($news->pluck('authorId')->filter()->all())->toBe([])
        ->and($news->pluck('status')->unique()->all())->toBe(['PUBLISHED']);

    // The planned project is data-wise "planned": no cover, no goal, not featured.
    $planned = DB::table('projects')->where('stage', 'PLANNED')->get(['coverImageId', 'goalAmount', 'isFeatured']);
    expect($planned)->toHaveCount(1)
        ->and($planned[0]->coverImageId)->toBeNull()
        ->and($planned[0]->goalAmount)->toBeNull()
        ->and((bool) $planned[0]->isFeatured)->toBeFalse();

    // Every photo has an Italian and an English description; none is remote; the flyer is not published.
    foreach (DB::table('media_assets')->get(['filename', 'alt', 'storageDriver', 'url']) as $asset) {
        $alt = json_decode((string) $asset->alt, true);
        expect(trim((string) ($alt['it'] ?? '')))->not->toBe('', "IT description of {$asset->filename}")
            ->and(trim((string) ($alt['en'] ?? '')))->not->toBe('', "EN description of {$asset->filename}")
            ->and($asset->storageDriver)->not->toBe('external')
            ->and((string) $asset->url)->not->toStartWith('http');
    }
    expect(DB::table('media_assets')->where('filename', 'raccolta-fondi-alluvione-volantino.jpg')->exists())->toBeFalse();

    // 2. A second plain run must refuse and change nothing (protects a live site with edits).
    DB::table('page_translations')->where('slug', 'chi-siamo')->update(['title' => 'Edited by the association']);
    $this->artisan('sakila:load-content')
        ->expectsOutputToContain('already has content')
        ->assertFailed();
    expect(contentCounts())->toBe($first)
        ->and(DB::table('page_translations')->where('slug', 'chi-siamo')->value('title'))->toBe('Edited by the association');

    // 3. --fresh replaces the content with an identical copy: same counts, same dates, no duplicate files.
    $this->artisan('sakila:load-content', ['--fresh' => true])->assertSuccessful();
    expect(contentCounts())->toBe($first)
        ->and(storedFiles($this->uploads))->toBe($files)
        ->and(DB::table('page_translations')->where('slug', 'chi-siamo')->value('title'))->toBe('Chi siamo')
        ->and(DB::table('news_posts')->orderBy('publishedAt')->pluck('publishedAt')->map(fn ($d) => substr((string) $d, 0, 16))->all())
        ->toBe(['2026-05-16 19:00', '2026-08-26 10:00'])
        ->and(DB::table('users')->where('id', $admin->id)->exists())->toBeTrue()
        ->and(DB::table('contact_submissions')->where('email', 'visitor@example.org')->exists())->toBeTrue()
        ->and(DB::table('newsletter_subscribers')->where('email', 'reader@example.org')->exists())->toBeTrue();
});

it('asks before replacing content in production, and does nothing when the answer is no', function () {
    $this->artisan('sakila:load-content')->assertSuccessful();
    $before = contentCounts();
    DB::table('page_translations')->where('slug', 'chi-siamo')->update(['title' => 'Edited by the association']);

    $this->app['env'] = 'production';
    try {
        $this->artisan('sakila:load-content', ['--fresh' => true])
            ->expectsConfirmation('Are you sure you want to run this command?', 'no')
            ->assertFailed();
    } finally {
        $this->app['env'] = 'testing';
    }

    expect(contentCounts())->toBe($before)
        ->and(DB::table('page_translations')->where('slug', 'chi-siamo')->value('title'))->toBe('Edited by the association');
});

it('content-check passes on the shipped dataset, lists the documented gaps, and fails on placeholder or withdrawn content', function () {
    $this->artisan('sakila:load-content')->assertSuccessful();

    // Clean dataset: exit 0, gaps are printed but do not fail the check…
    $gaps = require database_path('content/gaps.php');
    $this->artisan('sakila:content-check')
        ->expectsOutputToContain('No demo or placeholder content')
        ->expectsOutputToContain('Still needed from the association')
        ->doesntExpectOutputToContain('problem(s) to fix')
        ->assertSuccessful();
    expect($gaps)->not->toBeEmpty();

    // …unless --strict is used (go-live gate).
    $this->artisan('sakila:content-check', ['--strict' => true])->assertFailed();

    // A placeholder in public text is a problem.
    $id = DB::table('news_post_translations')->where('locale', 'it')->value('id');
    $excerpt = DB::table('news_post_translations')->where('id', $id)->value('excerpt');
    DB::table('news_post_translations')->where('id', $id)->update(['excerpt' => '[[TODO: testo]]']);
    $this->artisan('sakila:content-check')->expectsOutputToContain('Placeholder text in news_post_translations.excerpt')->assertFailed();
    DB::table('news_post_translations')->where('id', $id)->update(['excerpt' => $excerpt]);

    // A broken internal link in a story is a problem.
    $body = DB::table('news_post_translations')->where('id', $id)->value('body');
    DB::table('news_post_translations')->where('id', $id)->update(['body' => str_replace('aiuti-alluvione-2026', 'nope', (string) $body)]);
    $this->artisan('sakila:content-check')->expectsOutputToContain('Broken link /gallery/nope')->assertFailed();
    DB::table('news_post_translations')->where('id', $id)->update(['body' => $body]);

    // A photo without an Italian description is a problem.
    $assetId = DB::table('media_assets')->where('filename', 'alluvione-valle.jpg')->value('id');
    $alt = DB::table('media_assets')->where('id', $assetId)->value('alt');
    DB::table('media_assets')->where('id', $assetId)->update(['alt' => json_encode(['it' => ''])]);
    $this->artisan('sakila:content-check')->expectsOutputToContain('Photo without an Italian description: alluvione-valle.jpg')->assertFailed();
    DB::table('media_assets')->where('id', $assetId)->update(['alt' => $alt]);

    // A withdrawn photo that is still in the media library is a problem.
    DB::table('media_assets')->where('id', $assetId)->update(['filename' => 'raccolta-fondi-alluvione-volantino.jpg']);
    $this->artisan('sakila:content-check')->expectsOutputToContain('Withdrawn photo still in the media library')->assertFailed();
    DB::table('media_assets')->where('id', $assetId)->update(['filename' => 'alluvione-valle.jpg']);

    // The "example content" notice must be off.
    $features = json_decode((string) DB::table('site_settings')->value('features'), true);
    DB::table('site_settings')->update(['features' => json_encode(['demoContent' => true] + $features)]);
    $this->artisan('sakila:content-check')->expectsOutputToContain('example content')->assertFailed();
});
