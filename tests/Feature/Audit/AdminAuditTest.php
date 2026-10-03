<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\Role;
use App\Models\GalleryAlbum;
use App\Models\MediaAsset;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Settings\SettingsRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Regression tests for the admin findings of the production audit (docs/audit): QA-04, QA-05, QA-08,
 * QA-10, QA-11, QA-12, QA-14, QA-16, QA-18, CODE-06, CODE-10.
 */

beforeEach(function () {
    $this->withoutVite();
    $this->admin = UserAccounts::create('Asha Admin', 'admin@audit.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->editor = UserAccounts::create('Eli Editor', 'editor@audit.local', 'Correct-Horse-9!', Role::EDITOR);
    $this->author = UserAccounts::create('Arjun Author', 'author@audit.local', 'Correct-Horse-9!', Role::AUTHOR);
});

function auditNews(array $over = []): array
{
    $seo = ['seoTitle' => '', 'seoDescription' => ''];

    return array_replace_recursive([
        'publishedAt' => '', 'coverImageId' => null, 'categoryId' => null, 'projectId' => null, 'authorId' => null,
        'isFeatured' => false, 'tags' => [],
        'translations' => [
            'it' => ['title' => 'Una notizia', 'slug' => 'una-notizia', 'excerpt' => '', 'body' => ['type' => 'doc', 'content' => []], ...$seo],
            'en' => ['enabled' => false, 'title' => '', 'slug' => '', 'excerpt' => '', 'body' => ['type' => 'doc', 'content' => []], ...$seo],
        ],
    ], $over);
}

function auditAlbum(array $over = []): array
{
    $seo = ['seoTitle' => '', 'seoDescription' => ''];

    return [
        'coverImageId' => null, 'projectId' => null, 'takenAt' => '', 'position' => 0, 'publishedAt' => '', 'items' => [],
        'translations' => [
            'it' => ['title' => 'Un album', 'slug' => 'un-album', 'description' => '', ...$seo],
            'en' => ['enabled' => false, 'title' => '', 'slug' => '', 'description' => '', ...$seo],
        ],
        ...$over,
    ];
}

function auditSettingsRow(): void
{
    DB::table('site_settings')->insert([
        'id' => 'default', 'orgName' => 'Sakila per il Nepal', 'organization' => '{}', 'contact' => '{}', 'socialLinks' => '[]',
        'bankDetails' => '{}', 'nepalPayments' => '{}', 'donation' => '{}', 'impactStats' => '[]', 'seo' => '{}', 'appearance' => '{}',
        'analytics' => '{}', 'formMessages' => '{}', 'features' => '{}', 'updatedAt' => now()->format('Y-m-d H:i:s.v'),
    ]);
}

// ───────────── QA-04: stale references and out-of-range numbers are field errors, not a 500 ─────────────

it('reports a deleted category, project or author as a field error', function () {
    $this->actingAs($this->editor);
    $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'save', 'input' => auditNews(['categoryId' => 'gone', 'projectId' => 'gone-too', 'authorId' => 'nobody'])])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION')
        ->assertJsonPath('fieldErrors.categoryId.0', 'This category no longer exists. Choose another one.')
        ->assertJsonPath('fieldErrors.projectId.0', 'This project no longer exists. Choose another one.')
        ->assertJsonPath('fieldErrors.authorId.0', 'This author no longer exists. Choose another one.');
    expect(NewsPost::query()->count())->toBe(0);

    $this->postJson('/admin/actions/gallery/save', ['id' => null, 'intent' => 'save', 'input' => auditAlbum(['projectId' => 'gone'])])
        ->assertStatus(422)->assertJsonPath('fieldErrors.projectId.0', 'This project no longer exists. Choose another one.');
});

it('rejects an absurdly large list position with a validation error', function () {
    $this->actingAs($this->editor);
    $seo = ['seoTitle' => '', 'seoDescription' => ''];
    $body = ['type' => 'doc', 'content' => []];
    $this->postJson('/admin/actions/projects/save', ['id' => null, 'intent' => 'save', 'input' => [
        'stage' => 'ACTIVE', 'district' => '', 'latitude' => '', 'longitude' => '', 'startDate' => '', 'endDate' => '',
        'goalAmount' => '', 'raisedAmount' => '', 'currency' => 'EUR', 'coverImageId' => null, 'isFeatured' => false,
        'position' => '99999999999999999999', 'publishedAt' => '',
        'translations' => [
            'it' => ['title' => 'Progetto', 'slug' => 'progetto', 'sector' => '', 'metaLine' => '', 'summary' => '', 'body' => $body, ...$seo],
            'en' => ['enabled' => false, 'title' => '', 'slug' => '', 'sector' => '', 'metaLine' => '', 'summary' => '', 'body' => $body, ...$seo],
        ],
    ]])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['fieldErrors' => ['position']]);
});

// ───────────── QA-18: an album needs at least one item to go live ─────────────

it('does not publish an album without photos or videos', function () {
    $this->actingAs($this->editor);
    $this->postJson('/admin/actions/gallery/save', ['id' => null, 'intent' => 'publish', 'input' => auditAlbum()])
        ->assertStatus(422)->assertJsonPath('fieldErrors.items.0', 'A published album needs at least one photo or video');

    $id = $this->postJson('/admin/actions/gallery/save', ['id' => null, 'intent' => 'save', 'input' => auditAlbum()])->assertOk()->json('data.id');
    $this->postJson('/admin/actions/gallery/set-status', ['id' => $id, 'status' => 'PUBLISHED'])->assertStatus(422);
    expect(GalleryAlbum::query()->findOrFail($id)->status->value)->toBe('DRAFT');
});

// ───────────── QA-08: contact and bank details are validated when saved ─────────────

it('validates email, phone, IBAN and BIC formats', function () {
    expect(SettingsRules::isValidIban('IT88 V084 4053 6420 0000 0174 576'))->toBeTrue()
        ->and(SettingsRules::isValidIban('it60x0542811101000000123456'))->toBeTrue()
        ->and(SettingsRules::isValidIban('IT88 V084 4053 6420 0000 0174 577'))->toBeFalse()
        ->and(SettingsRules::isValidIban('IT00 0000 NOT AN IBAN'))->toBeFalse()
        ->and(SettingsRules::isValidEmail('info@example.org'))->toBeTrue()
        ->and(SettingsRules::isValidEmail('nope'))->toBeFalse()
        ->and(SettingsRules::isValidEmail('a b@example.org'))->toBeFalse()
        ->and(SettingsRules::isValidPhone('+39 392 812 2361'))->toBeTrue()
        ->and(SettingsRules::isValidPhone('abc'))->toBeFalse()
        ->and(SettingsRules::isValidPhone('12'))->toBeFalse()
        ->and(SettingsRules::isValidBic('ICRAITRRXXX'))->toBeTrue()
        ->and(SettingsRules::isValidBic('NOPE'))->toBeFalse();
});

it('refuses a mistyped contact email, phone or IBAN and keeps the stored value', function () {
    auditSettingsRow();
    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/settings/update', ['section' => 'contact', 'data' => [
        'contact' => ['email' => 'nope', 'phone' => 'abc'], 'socialLinks' => [],
    ]])->assertStatus(422)
        ->assertJsonPath('fieldErrors', fn ($e) => $e['contact.email'] === [SettingsRules::MESSAGES['email']] && $e['contact.phone'] === [SettingsRules::MESSAGES['phone']]);

    $donation = ['currency' => 'EUR', 'presetAmounts' => [10, 25], 'defaultAmount' => 25, 'impactNotes' => []];
    $this->postJson('/admin/actions/settings/update', ['section' => 'donations', 'data' => [
        'bankDetails' => ['accountHolder' => 'Sakila per il Nepal ODV', 'iban' => 'IT00 0000 NOT AN IBAN'], 'nepalPayments' => [], 'donation' => $donation,
    ]])->assertStatus(422)->assertJsonPath('fieldErrors', fn ($e) => $e['bankDetails.iban'] === [SettingsRules::MESSAGES['iban']]);
    $this->postJson('/admin/actions/settings/update', ['section' => 'donations', 'data' => [
        'bankDetails' => ['accountHolder' => 'Sakila per il Nepal ODV', 'iban' => 'IT88 V084 4053 6420 0000 0174 576'], 'nepalPayments' => [],
        'donation' => [...$donation, 'defaultAmount' => 50],
    ]])->assertStatus(422)->assertJsonPath('fieldErrors', fn ($e) => isset($e['donation.defaultAmount']));
    expect(json_decode((string) DB::table('site_settings')->value('bankDetails'), true))->toBe([]);

    // A correct IBAN, and "to be confirmed" placeholders, are accepted.
    $this->postJson('/admin/actions/settings/update', ['section' => 'donations', 'data' => [
        'bankDetails' => ['accountHolder' => '[[TODO: account holder]]', 'iban' => 'IT88 V084 4053 6420 0000 0174 576'], 'nepalPayments' => [], 'donation' => $donation,
    ]])->assertOk();
    $this->postJson('/admin/actions/settings/update', ['section' => 'contact', 'data' => [
        'contact' => ['email' => '[[TODO: contact email]]', 'phone' => '+39 392 812 2361'], 'socialLinks' => [],
    ]])->assertOk();
    expect(json_decode((string) DB::table('site_settings')->value('bankDetails'), true)['iban'])->toBe('IT88 V084 4053 6420 0000 0174 576');
});

// ───────────── QA-10: an installation without the content import can be set up from the admin ─────────────

it('creates the settings row on the first save when the install has none', function () {
    expect(DB::table('site_settings')->count())->toBe(0);
    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/settings/update', ['section' => 'contact', 'data' => [
        'contact' => ['email' => 'info@example.org'], 'socialLinks' => [],
    ]])->assertOk();
    $row = DB::table('site_settings')->first();
    expect($row)->not->toBeNull()
        ->and(json_decode((string) $row->contact, true)['email'])->toBe('info@example.org')
        ->and(json_decode((string) $row->donation, true)['defaultAmount'])->toBe(25);
});

it('offers and creates the missing system pages (home, privacy) as drafts', function () {
    $this->actingAs($this->admin);
    $this->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/dashboard')->where('data.missingSystemPages', ['home', 'privacy']));

    $this->postJson('/admin/actions/pages/create-system', ['key' => 'nope'])->assertStatus(422);
    $id = $this->postJson('/admin/actions/pages/create-system', ['key' => 'home'])->assertOk()->json('data.id');
    $page = Page::query()->with('translations')->findOrFail($id);
    expect($page->key)->toBe('home')
        ->and($page->status->value)->toBe('DRAFT')
        ->and($page->translations->pluck('slug', 'locale.value')->all())->toBe(['it' => 'home', 'en' => 'home']);
    $this->postJson('/admin/actions/pages/create-system', ['key' => 'home'])->assertStatus(409);
    $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('data.missingSystemPages', ['privacy'])->where('data.homePageId', $id));

    $this->actingAs($this->author);
    $this->postJson('/admin/actions/pages/create-system', ['key' => 'privacy'])->assertStatus(403);
    $this->get('/admin')->assertInertia(fn (Assert $p) => $p->where('data.missingSystemPages', []));
});

// ───────────── QA-05: an empty page is never published by accident ─────────────

it('never publishes an empty home page and asks before publishing any other empty page', function () {
    $this->actingAs($this->admin);
    $homeId = $this->postJson('/admin/actions/pages/create-system', ['key' => 'home'])->json('data.id');
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $homeId, 'locale' => 'it', 'confirmEmpty' => true])
        ->assertStatus(422)->assertJsonPath('fieldErrors.sections.0', 'EMPTY_HOME');

    $pageId = $this->postJson('/admin/actions/pages/create', ['title' => 'Vuota', 'slug' => 'vuota'])->json('data.id');
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.sections.0', 'EMPTY_PAGE');
    expect(PageTranslation::query()->where('pageId', $pageId)->value('publishedRevisionId'))->toBeNull();
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it', 'confirmEmpty' => true])
        ->assertOk()->assertJson(['message' => 'Published']);
});

// ───────────── QA-11: "published" with a future date is scheduled, not live ─────────────

it('says "scheduled" when the publication date is in the future', function () {
    $this->actingAs($this->editor);
    $tomorrow = Carbon::now('UTC')->addDay()->format('Y-m-d\TH:i:s.000\Z');
    $r = $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'publish', 'input' => auditNews(['publishedAt' => $tomorrow])])->assertOk();
    expect($r->json('message'))->toStartWith('Saved — scheduled')->not->toContain('live on the site');
    $id = $r->json('data.id');
    $this->get('/admin/news')->assertInertia(fn (Assert $p) => $p->where('list.items.0.scheduled', true));
    $this->postJson('/admin/actions/news/set-status', ['id' => $id, 'status' => 'PUBLISHED'])->assertOk()
        ->assertJsonPath('message', fn (string $m) => str_starts_with($m, 'Scheduled'));

    $now = $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'publish', 'input' => auditNews(['translations' => ['it' => ['slug' => 'subito']]])])->assertOk();
    expect($now->json('message'))->toBe('Saved — live on the site');
});

// ───────────── QA-12 / QA-16: not-found inside the admin shell; admin pages are never stored ─────────────

it('answers unknown admin addresses and deleted items with a 404 inside the admin shell', function () {
    $this->actingAs($this->editor);
    foreach (['/admin/nope', '/admin/some/deep/path', '/admin/news/gone', '/admin/projects/gone', '/admin/events/gone', '/admin/gallery/gone', '/admin/reports/gone', '/admin/pages/gone'] as $url) {
        $this->get($url)->assertStatus(404)->assertInertia(fn (Assert $p) => $p->component('admin/not-found')->has('backHref'));
    }
    // Anonymous visitors are still sent to the login page, not shown an admin screen.
    auth()->logout();
    $this->get('/admin/nope')->assertRedirect();
});

it('never lets the browser store admin screens (Back after signing out)', function () {
    $this->actingAs($this->editor);
    expect((string) $this->get('/admin')->assertOk()->headers->get('Cache-Control'))->toContain('no-store');
});

// ───────────── QA-14: validation messages are written for people ─────────────

it('returns friendly validation messages instead of the validator library text', function () {
    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'a--b'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.slug.0', 'Use lowercase letters, numbers and single dashes (e.g. chi-siamo)');
    $this->postJson('/admin/actions/pages/create', ['slug' => 'ok'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.title.0', 'Required');
    auditSettingsRow();
    $this->postJson('/admin/actions/settings/update', ['section' => 'organization', 'data' => ['organization' => ['legalName' => 'x', 'taxCode' => 'y', 'registeredOffice' => 'z'], 'features' => []]])
        ->assertStatus(422)->assertJsonPath('fieldErrors.orgName.0', 'Required');
});

// ───────────── CODE-06: every admin action is authorised explicitly ─────────────

it('guards every admin action with a capability (route middleware or an explicit in-controller check)', function () {
    // Content actions check `ContentCommon::requireCapability()` (role + ownership) inside the method.
    $checkedInController = [
        'news/save', 'news/set-status', 'news/delete', 'news/create-category',
        'projects/save', 'projects/set-status', 'projects/delete',
        'events/save', 'events/set-status', 'events/delete',
        'gallery/save', 'gallery/set-status', 'gallery/delete',
        'reports/save', 'reports/set-status', 'reports/delete',
        'media/update', 'media/delete',
    ];
    $unguarded = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/actions/')) {
            continue;
        }
        $name = substr($route->uri(), strlen('admin/actions/'));
        $middleware = $route->gatherMiddleware();
        expect($middleware)->toContain('admin.auth');
        $hasCapability = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'capability:'));
        if (! $hasCapability && ! in_array($name, $checkedInController, true)) {
            $unguarded[] = $name;
        }
    }
    expect($unguarded)->toBe([]);
});

// ───────────── CODE-10: renaming a user refreshes the public bylines ─────────────

it('expires the news cache when a user is renamed', function () {
    $this->actingAs($this->admin);
    $calls = new ArrayObject(['n' => 0]);
    $read = fn () => TagCache::remember([CacheTags::NEWS], 'audit:byline', fn () => ++$calls['n']);
    expect($read())->toBe(1)->and($read())->toBe(1);
    $this->postJson('/admin/actions/users/rename', ['userId' => $this->editor->id, 'name' => 'Elisabetta'])->assertOk();
    expect($read())->toBe(2);
});

it('keeps media library reads behind the media capability', function () {
    $asset = MediaAsset::query()->create([
        'kind' => 'IMAGE', 'storageDriver' => 'external', 'storageKey' => 'seed/audit.jpg', 'url' => '/uploads/x.jpg',
        'filename' => 'audit.jpg', 'mimeType' => 'image/jpeg', 'size' => 10, 'width' => 10, 'height' => 10, 'alt' => ['it' => 'Foto'],
    ]);
    $this->postJson('/admin/actions/media/get', ['id' => $asset->id])->assertStatus(401);
    $this->actingAs($this->author);
    $this->postJson('/admin/actions/media/get', ['id' => $asset->id])->assertOk()->assertJsonPath('data.id', $asset->id);
    $this->postJson('/admin/actions/media/search', ['q' => 'audit'])->assertOk();
});
