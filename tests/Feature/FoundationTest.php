<?php

declare(strict_types=1);

use App\Blocks\BlockRegistry;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Media\Assets;
use App\Settings\SiteSettings;
use App\Storage\Storage;
use App\Validation\JsonSchemas;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('creates the complete reference schema in utf8mb4_unicode_ci', function () {
    $tables = collect(DB::select('select table_name as t, table_collation as c from information_schema.tables where table_schema = database()'));
    $ddl = (string) file_get_contents(database_path('schema/sakila-mysql-init.sql'));
    preg_match_all('/CREATE TABLE `([a-z_]+)`/', $ddl, $m);
    expect($m[1])->toHaveCount(42);
    foreach ($m[1] as $table) {
        expect($tables->pluck('t')->all())->toContain($table);
    }
    expect($tables->where('c', '!=', 'utf8mb4_unicode_ci')->count())->toBe(0);
});

it('redirects / to /it and 404s unknown locales', function () {
    $this->get('/')->assertStatus(301)->assertRedirect('/it');
    $this->get('/fr')->assertNotFound();
    // The home page is the published "home" CMS page (no page → 404, like the reference).
    $this->seed(DatabaseSeeder::class);
    $this->get('/it')->assertOk()->assertSee('<html lang="it">', false)->assertSee('data-motion', false);
    $this->get('/en')->assertOk()->assertSee('<html lang="en">', false);
});

it('seeds the reference content: every section and settings column is valid', function () {
    $this->seed(DatabaseSeeder::class);
    $rows = DB::table('page_sections')->get()->map(fn ($r) => [
        'id' => $r->id, 'type' => $r->type, 'schemaVersion' => $r->schemaVersion, 'isVisible' => (bool) $r->isVisible,
        'content' => json_decode($r->content, true), 'settings' => json_decode((string) $r->settings, true),
    ]);
    $parsed = BlockRegistry::parseSections($rows);
    expect($rows)->toHaveCount(48)->and($parsed['errors'])->toBe([])
        ->and(app(SiteSettings::class)->forEditor()['invalid'])->toBe([])
        ->and(DB::table('users')->count())->toBe(3);
    $ids = array_keys(BlockRegistry::collectAssetIds($rows->all()));
    expect(Assets::byIds($ids, 'en'))->toHaveCount(count($ids));
});

it('validates block input with the exported Zod schemas (defaults, trim, strip, safe links)', function () {
    $ok = BlockRegistry::validateInput('hero', ['heading' => '  Ciao ', 'image' => ['assetId' => 'a1'], 'x' => 1], []);
    expect($ok['success'])->toBeTrue()
        ->and(JsonSchemas::encode($ok['settings']))->toBe('{"background":"paper","height":"full","overlay":"dark"}')
        ->and(JsonSchemas::encode($ok['content']))->toBe('{"heading":"Ciao","image":{"assetId":"a1"}}');
    $bad = BlockRegistry::validateInput('hero', ['heading' => 'x', 'image' => ['assetId' => 'a'], 'primaryCta' => ['label' => 'L', 'href' => 'javascript:alert(1)']], []);
    expect($bad['success'])->toBeFalse()->and($bad['issues'][0]['path'])->toBe(['content', 'primaryCta', 'href']);
    expect(BlockRegistry::validateInput('nope', [], [])['success'])->toBeFalse()
        ->and(BlockRegistry::typeNames())->toHaveCount(22);
});

it('invalidates tagged cache entries across keys', function () {
    $counter = new ArrayObject(['n' => 0]);
    $read = fn () => TagCache::remember([CacheTags::PROJECTS], 'projects:list:it', fn () => ++$counter['n']);
    expect($read())->toBe(1)->and($read())->toBe(1);
    TagCache::invalidate(CacheTags::PROJECTS);
    expect($read())->toBe(2);
});

it('never resurrects entries cached under an old tag version after a transient miss', function () {
    // The file store truncates before rewriting, so a concurrent reader can briefly see a tag
    // version as missing. That must not bring back entries cached before an invalidation.
    $counter = new ArrayObject(['n' => 0]);
    $read = fn () => TagCache::remember([CacheTags::NEWS], 'news:list:it', fn () => ++$counter['n']);
    expect($read())->toBe(1);
    TagCache::invalidate(CacheTags::NEWS);
    expect($read())->toBe(2);
    Cache::forget('tagv:'.CacheTags::NEWS); // simulated transient miss
    expect($read())->toBe(3)->and($read())->toBe(3);
});

it('serves local uploads safely', function () {
    config(['sakila.local_uploads_dir' => storage_path('framework/testing/uploads')]);
    Storage::reset();
    Storage::adapter()->put('2026/10/'.str_repeat('a', 32).'.pdf', "%PDF-1.4\n%%EOF", 'application/pdf');
    $url = '/uploads/2026/10/'.str_repeat('a', 32).'.pdf';
    $res = $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Type', 'application/pdf');
    $this->get($url, ['If-None-Match' => $res->headers->get('ETag')])->assertStatus(304);
    $this->get('/uploads/../.env')->assertNotFound();
    $this->get('/uploads/2026/10/x.svg')->assertNotFound();
    Storage::reset();
});

it('sends the reference security headers, and noindex on admin/preview', function () {
    $this->get('/it')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('X-Robots-Tag');
    expect($this->get('/it')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'")->toContain("object-src 'none'");
    $this->get('/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('reports health with a database ping and no details', function () {
    $this->get('/api/health')->assertOk()->assertJson(['status' => 'ok', 'db' => 'ok'])->assertHeader('Cache-Control', 'no-store, private');
    config(['database.connections.broken' => array_merge(config('database.connections.mysql'), ['port' => 1]), 'database.default' => 'broken']);
    try {
        $res = $this->get('/api/health')->assertStatus(503)->assertExactJson(['status' => 'error', 'db' => 'unreachable']);
        expect($res->headers->getCookies())->toBe([]);
    } finally {
        config(['database.default' => 'mysql']);
        DB::purge('broken');
    }
});
