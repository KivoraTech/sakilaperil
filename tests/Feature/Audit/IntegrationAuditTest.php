<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Engagement\Forms\FormContext;
use App\Enums\Role;
use App\Storage\StorageKeys;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Regression tests for audit findings that sat between two areas: QA-06 (consent link to an
 * unpublished privacy page), QA-19 (Devanagari file names), QA-12 (admin not-found for inbox items).
 */

it('points the consent link at the default-language policy when it is not published in this language', function () {
    $this->seed(DatabaseSeeder::class);
    $slug = fn (string $locale) => DB::table('page_translations as t')->join('pages as p', 'p.id', '=', 't.pageId')
        ->where('p.key', 'privacy')->where('t.locale', $locale)->value('t.slug');

    expect(FormContext::privacyHref('en'))->toBe('/en/'.$slug('en'));

    // Unpublish the English policy: the link must fall back to the Italian one, never to a 404.
    DB::table('page_translations as t')->join('pages as p', 'p.id', '=', 't.pageId')
        ->where('p.key', 'privacy')->where('t.locale', 'en')->update(['t.publishedRevisionId' => null]);
    TagCache::invalidate(CacheTags::PAGES);

    $href = FormContext::privacyHref('en');
    expect($href)->toBe('/it/'.$slug('it'));
    $this->get($href)->assertOk();
});

it('keeps combining marks in uploaded file names (Devanagari, accents)', function () {
    expect(StorageKeys::sanitizeFilename('नेपाल फोटो.jpg'))->toBe('नेपाल फोटो.jpg')
        ->and(StorageKeys::sanitizeFilename("cafe\u{0301}.png"))->toBe("cafe\u{0301}.png")
        ->and(StorageKeys::sanitizeFilename('../../etc/pass<wd>.txt'))->toBe('passwd.txt');
});

it('shows the admin not-found page for a missing message or donation', function () {
    $this->withoutVite();
    $admin = UserAccounts::create('Asha Admin', 'admin@audit.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);

    foreach (['/admin/donations/doesnotexist1234', '/admin/donations/not a valid id', '/admin/submissions/contact/doesnotexist1234'] as $url) {
        $this->actingAs($admin)->get($url)
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('admin/not-found'));
    }
});
