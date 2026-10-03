<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PagePreviewDraft;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Pages\Json;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Page-builder actions (ADMIN): validation → capability → write → audit → cache expiry, plus the
 * optimistic-concurrency token and the live-preview staging. Same rules as lib/actions/pages.ts.
 */

beforeEach(function () {
    $this->withoutVite();
    $this->admin = UserAccounts::create('Asha Admin', 'admin@test.local', 'Correct-Horse-9!', Role::ADMIN);
    $this->editor = UserAccounts::create('Eli Editor', 'editor@test.local', 'Correct-Horse-9!', Role::EDITOR);
    $this->author = UserAccounts::create('Arjun Author', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
    $this->image = MediaAsset::query()->create([
        'kind' => 'IMAGE', 'storageDriver' => 'local', 'storageKey' => 'images/a.jpg', 'url' => '/uploads/images/a.jpg',
        'filename' => 'a.jpg', 'mimeType' => 'image/jpeg', 'size' => 10, 'width' => 800, 'height' => 600,
        'alt' => ['it' => 'Bambini a scuola', 'en' => 'Children at school'],
    ]);
});

function newPage(object $test, string $slug = 'chi-siamo-test'): string
{
    $test->actingAs($test->admin);
    $r = $test->postJson('/admin/actions/pages/create', ['title' => 'Chi siamo', 'slug' => $slug])->assertOk();

    return $r->json('data.id');
}

function heroSection(object $test, string $heading = 'Ciao', ?string $id = null): array
{
    return array_filter([
        'id' => $id,
        'type' => 'hero',
        'isVisible' => true,
        'content' => [
            'heading' => $heading,
            'image' => ['assetId' => $test->image->id],
            'primaryCta' => ['label' => 'Dona', 'href' => '#dona'],
        ],
        'settings' => (object) [],
    ], fn ($v) => $v !== null);
}

function editorVersion(string $pageId, string $locale = 'it'): string
{
    return Json::iso(PageTranslation::query()->where('pageId', $pageId)->where('locale', $locale)->firstOrFail()->updatedAt);
}

it('creates a page with an Italian (and optional English) version, audited', function () {
    $this->actingAs($this->admin);
    $r = $this->postJson('/admin/actions/pages/create', [
        'title' => 'Progetto acqua', 'slug' => 'Progetto-Acqua', 'enTitle' => 'Water project', 'enSlug' => 'water-project',
    ])->assertOk()->assertJson(['ok' => true, 'message' => 'Page created']);
    $page = Page::query()->with('translations')->findOrFail($r->json('data.id'));
    expect($page->status->value)->toBe('DRAFT')
        ->and($page->translations->pluck('slug', 'locale.value')->all())->toBe(['it' => 'progetto-acqua', 'en' => 'water-project'])
        ->and(AuditLog::query()->where('action', 'PAGE_CREATED')->where('entityId', $page->id)->exists())->toBeTrue();
});

it('rejects reserved/invalid/duplicate slugs and authors', function () {
    $this->actingAs($this->author);
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'x'])->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'projects'])
        ->assertStatus(422)->assertJson(['code' => 'VALIDATION', 'fieldErrors' => ['slug' => ['This slug is reserved by the website']]]);
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'home'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.slug.0', '“home” is reserved for the home page');
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'a--b'])->assertStatus(422);
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'uno', 'enSlug' => 'one'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.enTitle.0', 'Enter the English title');
    $this->postJson('/admin/actions/pages/create', ['title' => 'X', 'slug' => 'uno'])->assertOk();
    $this->postJson('/admin/actions/pages/create', ['title' => 'Y', 'slug' => 'uno'])
        ->assertStatus(409)->assertJson(['code' => 'CONFLICT', 'fieldErrors' => ['slug' => ['Already used by another page']]]);
});

it('saves a draft: sections in order, {} kept, rich text sanitised, issues reported, no cache expiry', function () {
    $pageId = newPage($this);
    $pagesVersion = Cache::get('tagv:pages');
    $body = [
        'pageId' => $pageId, 'locale' => 'it', 'expectedVersion' => editorVersion($pageId),
        'meta' => ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test', 'seoTitle' => '', 'seoDescription' => 'Chi siamo', 'ogImageId' => '', 'noIndex' => false],
        'sections' => [
            heroSection($this),
            ['type' => 'richText', 'isVisible' => true, 'settings' => (object) [], 'content' => ['body' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]],
                ['type' => 'script', 'content' => []],
            ]]]],
            ['type' => 'hero', 'isVisible' => true, 'content' => ['heading' => ''], 'settings' => (object) []],
        ],
    ];
    $r = $this->postJson('/admin/actions/pages/save-draft', $body)->assertOk()
        ->assertJson(['ok' => true, 'message' => 'Draft saved — some sections need attention']);
    $ids = $r->json('data.sectionIds');
    expect($ids)->toHaveCount(3)
        ->and($r->json('data.version'))->toBe(editorVersion($pageId))
        ->and(collect($r->json('data.issues'))->pluck('position')->all())->toContain(3);

    $rows = DB::table('page_sections')->where('pageId', $pageId)->orderBy('position')->get();
    expect($rows->pluck('id')->all())->toBe($ids)
        ->and($rows[0]->settings)->toBe('{}')
        ->and(json_decode($rows[1]->content, true)['body']['content'])->toHaveCount(1)
        ->and(json_decode($rows[1]->content, true)['body']['content'][0]['content'][0])->not->toHaveKey('marks');
    $t = PageTranslation::query()->where('pageId', $pageId)->where('locale', 'it')->first();
    expect($t->hasDraftChanges)->toBeTrue()->and($t->seoTitle)->toBeNull()->and($t->seoDescription)->toBe('Chi siamo')
        ->and(Cache::get('tagv:pages'))->toBe($pagesVersion)
        ->and(AuditLog::query()->where('action', 'PAGE_UPDATED')->exists())->toBeTrue();

    // Second save updates existing rows in place and drops removed ones.
    $body['expectedVersion'] = $r->json('data.version');
    $body['sections'] = [heroSection($this, 'Nuovo', $ids[0])];
    $r2 = $this->postJson('/admin/actions/pages/save-draft', $body)->assertOk();
    expect($r2->json('data.sectionIds'))->toBe([$ids[0]])->and(PageSection::query()->where('pageId', $pageId)->count())->toBe(1);
});

it('rejects a stale editor with CONFLICT (optimistic concurrency)', function () {
    $pageId = newPage($this);
    $version = editorVersion($pageId);
    $body = ['pageId' => $pageId, 'locale' => 'it', 'expectedVersion' => $version, 'sections' => [],
        'meta' => ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test', 'noIndex' => false]];
    $this->postJson('/admin/actions/pages/save-draft', $body)->assertOk();
    $this->postJson('/admin/actions/pages/save-draft', $body)->assertStatus(409)->assertJson(['code' => 'CONFLICT']);
});

it('publishes only valid drafts, snapshots them and expires the public cache', function () {
    $pageId = newPage($this);
    $save = fn (array $sections) => $this->postJson('/admin/actions/pages/save-draft', [
        'pageId' => $pageId, 'locale' => 'it', 'sections' => $sections, 'meta' => ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test'],
    ])->assertOk();

    $bad = $save([['type' => 'hero', 'isVisible' => true, 'content' => ['heading' => 'Senza foto']]]);
    $badId = $bad->json('data.sectionIds.0');
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])
        ->assertStatus(422)->assertJson(['code' => 'VALIDATION'])->assertJsonStructure(['fieldErrors' => ["section:{$badId}"]]);

    $this->actingAs($this->author);
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])->assertStatus(403);

    $this->actingAs($this->editor);
    $save([heroSection($this), ['type' => 'hero', 'isVisible' => false, 'content' => ['heading' => 'Nascosto']]]);
    $before = Cache::get('tagv:pages');
    $r = $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])->assertOk()->assertJson(['message' => 'Published']);
    $t = PageTranslation::query()->where('pageId', $pageId)->where('locale', 'it')->first();
    $snapshot = json_decode((string) PageRevision::query()->findOrFail($r->json('data.revisionId'))->getRawOriginal('snapshot'), true);
    expect($t->publishedRevisionId)->toBe($r->json('data.revisionId'))
        ->and($t->hasDraftChanges)->toBeFalse()
        ->and(Page::query()->find($pageId)->status->value)->toBe('PUBLISHED')
        ->and($snapshot['sections'])->toHaveCount(1) // hidden section not published
        ->and($snapshot['sections'][0]['content']['heading'])->toBe('Ciao')
        ->and(Cache::get('tagv:pages'))->not->toBe($before)
        ->and(AuditLog::query()->where('action', 'PAGE_PUBLISHED')->exists())->toBeTrue();

    $this->postJson('/admin/actions/pages/unpublish', ['pageId' => $pageId, 'locale' => 'it'])->assertOk();
    expect(PageTranslation::query()->where('pageId', $pageId)->first()->publishedRevisionId)->toBeNull()
        ->and(Page::query()->find($pageId)->status->value)->toBe('DRAFT');
});

it('protects system pages and deletes custom pages', function () {
    $this->actingAs($this->admin);
    $home = Page::query()->create(['key' => 'home', 'status' => 'PUBLISHED']);
    PageTranslation::query()->create(['pageId' => $home->id, 'locale' => 'it', 'title' => 'Home', 'slug' => 'home', 'publishedRevisionId' => null]);
    $this->postJson('/admin/actions/pages/delete', ['pageId' => $home->id])->assertStatus(403);
    $this->postJson('/admin/actions/pages/unpublish', ['pageId' => $home->id, 'locale' => 'it'])->assertStatus(403);

    $pageId = newPage($this);
    $this->actingAs($this->editor);
    $this->postJson('/admin/actions/pages/delete', ['pageId' => $pageId])->assertStatus(403);
    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/pages/delete', ['pageId' => $pageId])->assertOk();
    expect(Page::query()->find($pageId))->toBeNull()->and(PageTranslation::query()->where('pageId', $pageId)->exists())->toBeFalse();
});

it('restores a published version into the draft and copies sections between languages', function () {
    $pageId = newPage($this);
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this, 'Live')],
        'meta' => ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test']])->assertOk();
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])->assertOk();
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this, 'Bozza')],
        'meta' => ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test']])->assertOk();

    $this->postJson('/admin/actions/pages/restore', ['pageId' => $pageId, 'locale' => 'it'])->assertOk()->assertJson(['message' => 'Draft restored']);
    $row = PageSection::query()->where('pageId', $pageId)->where('locale', 'it')->firstOrFail();
    expect($row->content['heading'])->toBe('Live')
        ->and(PageTranslation::query()->where('pageId', $pageId)->where('locale', 'it')->first()->hasDraftChanges)->toBeFalse();

    $this->postJson('/admin/actions/pages/create-translation', ['pageId' => $pageId, 'locale' => 'en', 'title' => 'About', 'slug' => 'about-test', 'copyFrom' => 'it'])
        ->assertOk();
    expect(PageSection::query()->where('pageId', $pageId)->where('locale', 'en')->count())->toBe(1);
    $this->postJson('/admin/actions/pages/copy-sections', ['pageId' => $pageId, 'from' => 'it', 'to' => 'en'])
        ->assertOk()->assertJson(['data' => ['count' => 1]]);
    $this->postJson('/admin/actions/pages/copy-sections', ['pageId' => $pageId, 'from' => 'it', 'to' => 'it'])->assertStatus(422);
});

it('stages unsaved editor state for the live preview (per user, per tab) without touching the draft', function () {
    $pageId = newPage($this);
    $stage = fn (string $clientId, string $heading) => $this->postJson('/admin/actions/pages/stage-preview', [
        'pageId' => $pageId, 'locale' => 'it', 'clientId' => $clientId, 'title' => 'Chi siamo',
        'sections' => [[...heroSection($this, $heading), 'key' => 'new-1']],
    ]);
    $stage('tab-aaaaaaaa', 'Anteprima viva')->assertOk()->assertJsonStructure(['data' => ['stagedAt']]);
    $stage('bad id!', 'x')->assertStatus(422);
    expect(PagePreviewDraft::query()->count())->toBe(1)
        ->and(PageSection::query()->where('pageId', $pageId)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'PAGE_UPDATED')->exists())->toBeFalse();

    $this->get("/preview/it/pages/{$pageId}?draft=tab-aaaaaaaa&embed=1")
        ->assertOk()->assertSee('Anteprima viva')->assertSee('data-section-id="new-1"', false)->assertDontSee('preview-banner')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    // Another tab id, or the plain preview, shows the SAVED draft.
    $this->get("/preview/it/pages/{$pageId}?draft=tab-bbbbbbbb")->assertOk()->assertDontSee('Anteprima viva')->assertSee('ANTEPRIMA / PREVIEW — NOT LIVE');

    // Another user never sees it.
    $this->actingAs($this->editor);
    $this->get("/preview/it/pages/{$pageId}?draft=tab-aaaaaaaa&embed=1")->assertOk()->assertDontSee('Anteprima viva');
});

it('keeps previews behind a session', function () {
    $pageId = newPage($this);
    auth()->logout();
    $this->get("/preview/it/pages/{$pageId}")->assertRedirect('/admin/login');
});

it('lets only publishers change the address or indexing of a live page', function () {
    $pageId = newPage($this);
    $meta = ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test'];
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this)], 'meta' => $meta])->assertOk();
    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])->assertOk();

    $this->actingAs($this->author);
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this)],
        'meta' => [...$meta, 'slug' => 'nuovo-indirizzo']])->assertStatus(403);
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this)], 'meta' => $meta])->assertOk();

    $this->actingAs($this->editor);
    $before = Cache::get('tagv:pages');
    $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [heroSection($this)],
        'meta' => [...$meta, 'slug' => 'nuovo-indirizzo']])->assertOk();
    expect(Cache::get('tagv:pages'))->not->toBe($before);
});

it('reads action bodies raw: rich-text spaces and empty strings survive TrimStrings/ConvertEmptyStringsToNull', function () {
    $pageId = newPage($this);
    $doc = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'a '],
        ['type' => 'text', 'text' => 'b', 'marks' => [['type' => 'bold']]],
    ]]]];
    $section = ['type' => 'richText', 'isVisible' => true, 'settings' => (object) [], 'content' => ['eyebrow' => '', 'body' => $doc]];
    $meta = ['title' => 'Chi siamo', 'slug' => 'chi-siamo-test', 'seoTitle' => '', 'seoDescription' => '', 'ogImageId' => ''];

    $r = $this->postJson('/admin/actions/pages/save-draft', ['pageId' => $pageId, 'locale' => 'it', 'sections' => [$section], 'meta' => $meta])->assertOk();
    $stored = json_decode((string) DB::table('page_sections')->where('id', $r->json('data.sectionIds.0'))->value('content'), true);
    expect($stored['body'])->toBe($doc)->and($stored['eyebrow'])->toBe('');

    $this->postJson('/admin/actions/pages/publish', ['pageId' => $pageId, 'locale' => 'it'])->assertOk();
    $snapshot = json_decode((string) DB::table('page_revisions')->where('pageId', $pageId)->value('snapshot'), true);
    expect($snapshot['sections'][0]['content']['body'])->toBe($doc);

    $this->postJson('/admin/actions/pages/stage-preview', ['pageId' => $pageId, 'locale' => 'it', 'clientId' => 'tab-cccccccc',
        'sections' => [[...$section, 'key' => 'k1']]])->assertOk();
    $staged = json_decode((string) DB::table('page_preview_drafts')->value('sections'), true);
    expect($staged[0]['content']['body'])->toBe($doc)->and($staged[0]['content']['eyebrow'])->toBe('');

    // Navigation keeps an English label with a trailing space trimmed by Zod rules, but never null-converts.
    $this->postJson('/admin/actions/navigation/save', ['key' => 'footer-legal', 'items' => [
        ['label' => ['it' => 'Privacy', 'en' => ''], 'linkType' => 'ROUTE', 'routeKey' => 'privacy'],
    ]])->assertOk();
    expect(NavigationItem::query()->latest('createdAt')->first()->label)->toBe(['it' => 'Privacy']);

    // Users: a blank optional password means "generate one", not a validation error on null.
    $this->postJson('/admin/actions/users/create', ['name' => 'Raw Body', 'email' => 'raw@test.local', 'role' => 'AUTHOR', 'password' => ''])
        ->assertOk()->assertJsonStructure(['data' => ['temporaryPassword']]);
});
