<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\NewsPostTranslation;
use App\Models\NewsTagTranslation;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\Report;

/*
| CONTENT actions (/admin/actions/{news,projects,events,gallery,reports}/*): port of the reference's
| content-actions + content-schemas tests. Every action: validate → capability → write → audit → tags.
*/

beforeEach(function () {
    $this->admin = UserAccounts::create('Ada Admin', 'admin@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->editor = UserAccounts::create('Eva Editor', 'editor@test.local', 'Correct-Horse-9!', Role::EDITOR);
    $this->author = UserAccounts::create('Arjun Author', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
    $this->other = UserAccounts::create('Olga Author', 'olga@test.local', 'Correct-Horse-9!', Role::AUTHOR);
});

function doc_(mixed ...$content): array
{
    return ['type' => 'doc', 'content' => $content];
}

function tr_(array $extra = [], array $en = []): array
{
    $seo = ['seoTitle' => '', 'seoDescription' => ''];

    return [
        'it' => ['title' => 'Una scuola', 'slug' => 'una-scuola', ...$seo, ...$extra],
        'en' => ['enabled' => false, 'title' => '', 'slug' => '', ...$seo, ...$extra, ...$en],
    ];
}

function news_input(array $over = []): array
{
    return array_replace_recursive([
        'publishedAt' => '', 'coverImageId' => null, 'categoryId' => null, 'projectId' => null, 'authorId' => null,
        'isFeatured' => false, 'tags' => [],
        'translations' => tr_(['excerpt' => '', 'body' => doc_()]),
    ], $over);
}

function image_asset(string $altIt = ''): MediaAsset
{
    return MediaAsset::query()->create([
        'kind' => 'IMAGE', 'storageDriver' => 'external', 'storageKey' => 'seed/'.bin2hex(random_bytes(6)).'.jpg',
        'url' => '/uploads/x.jpg', 'filename' => 'cover-'.bin2hex(random_bytes(2)).'.jpg', 'mimeType' => 'image/jpeg', 'size' => 10,
        'width' => 10, 'height' => 10, 'alt' => $altIt !== '' ? ['it' => $altIt] : null,
    ]);
}

describe('news', function () {
    it('rejects anonymous callers', function () {
        $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'save'])->assertStatus(401);
    });

    it('lets an author save a draft but not publish; sanitises rich text without trimming its spaces', function () {
        $this->actingAs($this->author);
        $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'publish'])
            ->assertStatus(403)->assertJson(['ok' => false, 'code' => 'FORBIDDEN', 'message' => 'Only editors can publish.']);

        $body = doc_(
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' and normal '],
                ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
            ]],
            ['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]],
        );
        $res = $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'save', 'input' => news_input([
            'tags' => ['Scuola', 'scuola ', 'Acqua pulita'],
            'translations' => ['it' => ['title' => '  Una scuola  ', 'body' => $body, 'excerpt' => '']],
        ])])->assertOk()->assertJson(['ok' => true, 'message' => 'Draft saved']);

        $post = NewsPost::query()->findOrFail($res->json('data.id'));
        $it = NewsPostTranslation::query()->where('postId', $post->id)->where('locale', 'it')->firstOrFail();
        expect($post->status->value)->toBe('DRAFT')
            ->and($post->authorId)->toBe($this->author->id)
            ->and($it->title)->toBe('Una scuola')
            ->and($it->excerpt)->toBeNull()
            ->and($it->body)->toBe(doc_(['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'bold', 'marks' => [['type' => 'bold']]],
                ['type' => 'text', 'text' => ' and normal '],
                ['type' => 'text', 'text' => 'x'],
            ]]))
            ->and(NewsTagTranslation::query()->pluck('slug')->sort()->values()->all())->toBe(['acqua-pulita', 'scuola'])
            ->and(AuditLog::where('action', 'CONTENT_CREATED')->where('entityId', $post->id)->exists())->toBeTrue();
    });

    it('stops authors editing other people\'s posts or published posts', function () {
        $this->actingAs($this->other);
        $id = $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'save'])->json('data.id');
        $this->actingAs($this->author);
        $this->postJson('/admin/actions/news/save', ['id' => $id, 'input' => news_input(), 'intent' => 'save'])
            ->assertStatus(403)->assertJson(['message' => 'You can only edit your own posts.']);

        $this->actingAs($this->author);
        $mine = $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(['translations' => ['it' => ['slug' => 'mio']]]), 'intent' => 'save'])->json('data.id');
        NewsPost::query()->whereKey($mine)->update(['status' => 'PUBLISHED']);
        $this->postJson('/admin/actions/news/save', ['id' => $mine, 'input' => news_input(['translations' => ['it' => ['slug' => 'mio']]]), 'intent' => 'save'])
            ->assertStatus(403)->assertJson(['message' => 'This post is published or archived. Ask an editor to change it.']);
    });

    it('validates input on the server with the form field paths', function () {
        $this->actingAs($this->editor);
        $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'save', 'input' => news_input([
            'publishedAt' => 'yesterday',
            'tags' => [str_repeat('t', 41)],
            'translations' => [
                'it' => ['title' => '', 'slug' => 'Bad Slug!'],
                'en' => ['enabled' => true, 'title' => '', 'slug' => ''],
            ],
        ])])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['fieldErrors' => [
            'publishedAt', 'tags.0', 'translations.it.title', 'translations.it.slug', 'translations.en.title', 'translations.en.slug',
        ]])
            ->assertJsonPath('fieldErrors', fn ($e) => $e['translations.it.slug'] === ['Lowercase letters, numbers and dashes only']
                && $e['translations.it.title'] === ['Title is required']);
        $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'nope', 'input' => news_input()])->assertStatus(422);
    });

    it('reports slug conflicts as field errors', function () {
        $this->actingAs($this->editor);
        $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'save'])->assertOk();
        $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'save'])
            ->assertStatus(409)->assertJson(['code' => 'CONFLICT', 'fieldErrors' => ['translations.it.slug' => ['This slug is already used by another item in this language.']]]);
    });

    it('requires Italian alt text on images before publishing, then publishes and expires the cache', function () {
        $this->actingAs($this->editor);
        $cover = image_asset();
        $inline = image_asset();
        TagCache::remember([CacheTags::NEWS], 'probe', fn () => 'old');
        $input = news_input(['coverImageId' => $cover->id, 'translations' => ['it' => ['body' => doc_(
            ['type' => 'image', 'attrs' => ['src' => '/uploads/x.jpg', 'assetId' => $inline->id]],
        )]]]);
        $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => $input, 'intent' => 'publish'])
            ->assertStatus(422)->assertJsonPath('message', "Add alt text (Italian) to these images in the Media library before publishing: {$cover->filename}, {$inline->filename}");

        $cover->update(['alt' => ['it' => 'Copertina']]);
        $inline->update(['alt' => ['it' => 'Foto']]);
        $id = $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => $input, 'intent' => 'publish'])
            ->assertOk()->assertJson(['message' => 'Saved — live on the site'])->json('data.id');
        $post = NewsPost::query()->findOrFail($id);
        expect($post->status->value)->toBe('PUBLISHED')->and($post->publishedAt)->not->toBeNull()
            ->and(AuditLog::where('action', 'CONTENT_PUBLISHED')->where('entityId', $id)->count())->toBe(1)
            ->and(TagCache::remember([CacheTags::NEWS], 'probe', fn () => 'new'))->toBe('new');

        $this->postJson('/admin/actions/news/set-status', ['id' => $id, 'status' => 'ARCHIVED'])->assertOk()->assertJson(['message' => 'Archived']);
        $this->postJson('/admin/actions/news/set-status', ['id' => $id, 'status' => 'DRAFT'])->assertOk()->assertJson(['message' => 'Moved to drafts']);
        expect(AuditLog::where('action', 'CONTENT_ARCHIVED')->count())->toBe(1)->and(AuditLog::where('action', 'CONTENT_UNPUBLISHED')->count())->toBe(1);

        $this->actingAs($this->author);
        $this->postJson('/admin/actions/news/delete', ['id' => $id])->assertStatus(403);
        $this->actingAs($this->editor);
        $this->postJson('/admin/actions/news/delete', ['id' => $id])->assertOk()->assertJson(['message' => 'Post deleted']);
        expect(NewsPost::find($id))->toBeNull()->and(NewsPostTranslation::where('postId', $id)->count())->toBe(0);
    });

    it('keeps English in sync with the "enabled" switch', function () {
        $this->actingAs($this->editor);
        $en = ['enabled' => true, 'title' => 'A school', 'slug' => 'a-school', 'excerpt' => '', 'body' => doc_()];
        $id = $this->postJson('/admin/actions/news/save', ['id' => null, 'intent' => 'save', 'input' => news_input(['translations' => ['en' => $en]])])->json('data.id');
        expect(NewsPostTranslation::where('postId', $id)->pluck('locale')->map->value->sort()->values()->all())->toBe(['en', 'it']);
        $this->postJson('/admin/actions/news/save', ['id' => $id, 'intent' => 'save', 'input' => news_input()])->assertOk();
        expect(NewsPostTranslation::where('postId', $id)->count())->toBe(1);
    });

    it('creates categories (editors only) and refuses duplicates', function () {
        $this->actingAs($this->author);
        $this->postJson('/admin/actions/news/create-category', ['input' => ['nameIt' => 'Scuole', 'nameEn' => '']])->assertStatus(403);
        $this->actingAs($this->editor);
        $this->postJson('/admin/actions/news/create-category', ['input' => ['nameIt' => 'Scuole', 'nameEn' => 'Schools']])
            ->assertOk()->assertJsonPath('data.name', 'Scuole')->assertJsonPath('message', 'Category created');
        $this->postJson('/admin/actions/news/create-category', ['input' => ['nameIt' => 'scuole', 'nameEn' => '']])
            ->assertStatus(409)->assertJson(['message' => 'A category with this name already exists.']);
        $this->postJson('/admin/actions/news/create-category', ['input' => ['nameIt' => '!!!', 'nameEn' => '']])
            ->assertStatus(422)->assertJson(['fieldErrors' => ['nameIt' => ['Invalid name']]]);
        expect(NewsCategory::count())->toBe(1);
    });
});

describe('projects & events', function () {
    it('saves projects with money in minor units and validates dates and coordinates', function () {
        $this->actingAs($this->author);
        $input = [
            'stage' => 'ACTIVE', 'district' => 'Sindhupalchok', 'latitude' => '27.95', 'longitude' => '85.68',
            'startDate' => '2026-01-01T00:00:00.000Z', 'endDate' => '', 'goalAmount' => '1500.5', 'raisedAmount' => '',
            'currency' => 'EUR', 'coverImageId' => null, 'isFeatured' => false, 'position' => '3', 'publishedAt' => '',
            'translations' => tr_(['sector' => 'Educazione', 'metaLine' => '', 'summary' => '', 'body' => doc_()]),
        ];
        $id = $this->postJson('/admin/actions/projects/save', ['id' => null, 'input' => $input, 'intent' => 'save'])->assertOk()->json('data.id');
        $p = Project::query()->findOrFail($id);
        expect($p->goalAmount)->toBe(150050)->and($p->raisedAmount)->toBeNull()->and($p->position)->toBe(3)
            ->and($p->createdById)->toBe($this->author->id)->and($p->latitude)->toBe(27.95);

        $this->postJson('/admin/actions/projects/save', ['id' => $id, 'intent' => 'save', 'input' => [
            ...$input, 'latitude' => '91', 'goalAmount' => '12,50', 'endDate' => '2025-01-01T00:00:00.000Z',
        ]])->assertStatus(422)->assertJsonStructure(['fieldErrors' => ['latitude', 'goalAmount']]);
        $this->postJson('/admin/actions/projects/save', ['id' => $id, 'intent' => 'save', 'input' => [...$input, 'endDate' => '2025-01-01T00:00:00.000Z']])
            ->assertStatus(422)->assertJson(['fieldErrors' => ['endDate' => ['The end must be after the start']]]);

        $this->actingAs($this->editor);
        ProjectTranslation::query()->where('projectId', $id)->delete();
        Donation::query()->create([
            'reference' => 'SKL-2026-ABCDEFGH', 'provider' => 'BANK_TRANSFER', 'status' => 'PENDING', 'amount' => 1000,
            'currency' => 'EUR', 'locale' => 'it', 'projectId' => $id,
        ]);
        $this->postJson('/admin/actions/projects/delete', ['id' => $id])
            ->assertStatus(409)->assertJson(['message' => 'This project has donations linked to it. Archive it instead of deleting it.']);
    });

    it('saves events and requires a start after which the end comes', function () {
        $this->actingAs($this->editor);
        $base = [
            'startsAt' => '2026-11-10T18:30:00.000Z', 'endsAt' => '', 'allDay' => false, 'timezone' => 'Europe/Rome',
            'address' => 'Via Roma 1', 'mapUrl' => '', 'onlineUrl' => '', 'registrationUrl' => '', 'coverImageId' => null,
            'isFeatured' => false, 'publishedAt' => '',
            'translations' => tr_(['summary' => '', 'locationName' => 'Sala', 'body' => doc_()]),
        ];
        $this->postJson('/admin/actions/events/save', ['id' => null, 'intent' => 'save', 'input' => [
            ...$base, 'startsAt' => '', 'mapUrl' => 'http://maps.example', 'timezone' => 'Mars/Base',
        ]])->assertStatus(422)->assertJson(['fieldErrors' => [
            'startsAt' => ['Start date is required'], 'mapUrl' => ['Use a full https:// link'],
        ]])->assertJsonStructure(['fieldErrors' => ['timezone']]);
        $this->postJson('/admin/actions/events/save', ['id' => null, 'intent' => 'save', 'input' => [...$base, 'endsAt' => '2026-11-10T17:00:00.000Z']])
            ->assertStatus(422)->assertJson(['fieldErrors' => ['endsAt' => ['The end must be after the start']]]);
        $id = $this->postJson('/admin/actions/events/save', ['id' => null, 'intent' => 'publish', 'input' => $base])->assertOk()->json('data.id');
        expect(Event::query()->findOrFail($id)->status->value)->toBe('PUBLISHED')
            ->and(Event::query()->findOrFail($id)->startsAt->toIso8601String())->toBe('2026-11-10T18:30:00+00:00');
    });
});

describe('gallery & reports', function () {
    it('albums require editor rights and accept only valid videos and photos with an asset', function () {
        $this->actingAs($this->author);
        $album = ['coverImageId' => null, 'projectId' => null, 'takenAt' => '', 'position' => 0, 'publishedAt' => '', 'items' => [],
            'translations' => tr_(['description' => ''])];
        $this->postJson('/admin/actions/gallery/save', ['id' => null, 'input' => $album, 'intent' => 'save'])->assertStatus(403);

        $this->actingAs($this->editor);
        $photo = image_asset('Bambini');
        $item = fn (array $o) => ['key' => 'k', 'type' => 'PHOTO', 'mediaAssetId' => null, 'videoProvider' => null, 'videoId' => null, 'captionIt' => '', 'captionEn' => '', 'previewUrl' => null, ...$o];
        $this->postJson('/admin/actions/gallery/save', ['id' => null, 'intent' => 'save', 'input' => [...$album, 'items' => [
            $item([]),
            $item(['type' => 'VIDEO', 'videoProvider' => 'youtube', 'videoId' => '"><script>']),
        ]]])->assertStatus(422)->assertJson(['fieldErrors' => [
            'items.0.mediaAssetId' => ['Choose a photo'], 'items.1.videoId' => ['Invalid YouTube/Vimeo video'],
        ]]);
        $id = $this->postJson('/admin/actions/gallery/save', ['id' => null, 'intent' => 'publish', 'input' => [...$album, 'items' => [
            $item(['mediaAssetId' => $photo->id, 'captionIt' => 'Scuola', 'captionEn' => '']),
            $item(['type' => 'VIDEO', 'videoProvider' => 'vimeo', 'videoId' => '76979871', 'captionEn' => 'Video']),
        ]]])->assertOk()->json('data.id');
        $items = GalleryItem::query()->where('albumId', $id)->orderBy('position')->get();
        expect($items)->toHaveCount(2)
            ->and($items[0]->caption)->toBe(['it' => 'Scuola'])
            ->and($items[1]->videoProvider->value)->toBe('VIMEO')
            ->and($items[1]->caption)->toBe(['it' => '', 'en' => 'Video'])
            ->and(GalleryAlbum::query()->findOrFail($id)->status->value)->toBe('PUBLISHED');
    });

    it('reports need a PDF to be published', function () {
        $this->actingAs($this->editor);
        $input = ['type' => 'ANNUAL_REPORT', 'year' => '2025', 'fileId' => null, 'coverImageId' => null, 'position' => 0, 'publishedAt' => '',
            'translations' => ['it' => ['title' => 'Bilancio 2025', 'description' => ''], 'en' => ['enabled' => false, 'title' => '', 'description' => '', 'fileId' => null]]];
        $this->postJson('/admin/actions/reports/save', ['id' => null, 'input' => $input, 'intent' => 'publish'])
            ->assertStatus(422)->assertJson(['message' => 'Attach the PDF before publishing.', 'fieldErrors' => ['fileId' => ['A published report needs a PDF']]]);
        $image = image_asset('x');
        $this->postJson('/admin/actions/reports/save', ['id' => null, 'input' => [...$input, 'fileId' => $image->id], 'intent' => 'save'])
            ->assertStatus(422)->assertJson(['fieldErrors' => ['fileId' => ['Choose a PDF document.']]]);
        $this->postJson('/admin/actions/reports/save', ['id' => null, 'input' => [...$input, 'year' => 1989], 'intent' => 'save'])
            ->assertStatus(422)->assertJsonStructure(['fieldErrors' => ['year']]);
        $id = $this->postJson('/admin/actions/reports/save', ['id' => null, 'input' => $input, 'intent' => 'save'])->assertOk()->json('data.id');
        expect(Report::query()->findOrFail($id)->year)->toBe(2025);
        $this->postJson('/admin/actions/reports/set-status', ['id' => $id, 'status' => 'PUBLISHED'])->assertStatus(422);
    });
});

describe('pages and previews', function () {
    it('renders list and editor pages with the reference props and gates editor-only screens', function () {
        $this->actingAs($this->editor);
        $id = $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(), 'intent' => 'save'])->json('data.id');
        $this->get('/admin/news?status=DRAFT&q=scuola&page=x')->assertOk()->assertInertia(fn ($p) => $p
            ->component('admin/news/index')->where('params', ['q' => 'scuola', 'status' => 'DRAFT', 'page' => 1])
            ->where('list.total', 1)->where('list.items.0.title', 'Una scuola')->where('list.items.0.meta', 'Eva Editor'));
        $this->get("/admin/news/{$id}")->assertOk()->assertInertia(fn ($p) => $p->component('admin/news/edit')
            ->where('initialValues.translations.it.slug', 'una-scuola')->where('initialValues.translations.en.enabled', false)
            ->where('perms', ['canEdit' => true, 'canPublish' => true, 'canDelete' => true]));
        $this->get('/admin/news/does-not-exist')->assertNotFound();
        foreach (['/admin/projects', '/admin/events?when=past', '/admin/gallery', '/admin/reports?q=2025', '/admin/projects/new', '/admin/events/new', '/admin/gallery/new', '/admin/reports/new', '/admin/news/new'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->author);
        $this->get('/admin/gallery/new')->assertRedirect('/admin/access-denied');
        $this->get('/admin/reports/new')->assertRedirect('/admin/access-denied');
        $this->get('/admin/gallery')->assertOk()->assertInertia(fn ($p) => $p->where('canManage', false));
    });

    it('serves session-gated, noindex draft previews', function () {
        $this->actingAs($this->editor);
        $id = $this->postJson('/admin/actions/news/save', ['id' => null, 'input' => news_input(['translations' => ['it' => ['body' => doc_(
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Testo della bozza']]],
        )]]]), 'intent' => 'save'])->json('data.id');
        $this->get("/preview/en/news/{$id}")->assertOk()
            ->assertSee('ANTEPRIMA / PREVIEW — NOT LIVE')->assertSee('News story · Una scuola · Draft, not live · No English version yet: showing Italian')
            ->assertSee('Testo della bozza')->assertSee('noindex, nofollow, nocache', false)->assertHeader('X-Robots-Tag');
        $this->get('/preview/it/news/nope')->assertNotFound();
        auth()->logout();
        $this->get("/preview/it/news/{$id}")->assertRedirect();
    });
});
