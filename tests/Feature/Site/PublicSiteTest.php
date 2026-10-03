<?php

declare(strict_types=1);

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\ContentStatus;
use App\Models\NewsPost;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;

/* routes, drafts never public, SEO files, redirects (ports of the reference behaviour). */

beforeEach(function () {
    config(['sakila.public_cache' => false]);
    $this->seed(DatabaseSeeder::class);
});

it('renders the home page from the published snapshot with chrome and SEO', function () {
    $res = $this->get('/it')->assertOk();
    $html = $res->getContent();
    expect($html)->toContain('<html lang="it">')
        ->toContain('data-block="hero"')
        ->toContain('id="site-header"')
        ->toContain('<link rel="canonical" href="'.rtrim((string) config('sakila.site_url'), '/').'/it">')
        ->toContain('hreflang="x-default"')
        ->toContain('"@type":"NGO"');
});

it('serves every public route', function (string $url, int $status) {
    $this->get($url)->assertStatus($status);
})->with([
    ['/en', 200], ['/it/chi-siamo', 200], ['/en/about-us', 200], ['/it/projects', 200], ['/it/news', 200],
    ['/it/events', 200], ['/it/gallery', 200], ['/it/donate', 200], ['/it/donate/thank-you', 200],
    ['/it/donate/cancelled', 200], ['/it/newsletter', 200], ['/it/trasparenza', 200], ['/it/contatti', 200],
    ['/it/pagina-che-non-esiste', 404], ['/it/projects/nope', 404], ['/it/a/b', 404],
]);

it('redirects unprefixed paths to Italian, a page slug to its own locale, and 404s unknown locales', function () {
    $this->get('/foo')->assertNotFound(); // unknown locale-less paths are a 404 in one hop (audit SEO-07)
    $this->get('/projects')->assertRedirect('/it/projects')->assertStatus(301);
    $this->get('/en/chi-siamo')->assertRedirect('/en/about-us');
    $this->get('/fr')->assertNotFound();
    $this->get('/de/about')->assertNotFound();
});

it('never shows draft news publicly', function () {
    $post = NewsPost::query()->with('translations')->firstOrFail();
    $slug = $post->translations->firstWhere('locale.value', 'it')?->slug ?? $post->translations->first()->slug;
    $this->get('/it/news/'.$slug)->assertOk();
    $post->update(['status' => ContentStatus::DRAFT]);
    TagCache::invalidate(CacheTags::NEWS);
    $this->get('/it/news/'.$slug)->assertNotFound();
    expect($this->get('/it/news')->getContent())->not->toContain('/it/news/'.$slug.'"');
});

it('shows news immediately after publishing (millisecond-precise date bindings)', function () {
    // publishedAt is DATETIME(3); a binding truncated to whole seconds hid the item for up to 1 s.
    $post = NewsPost::query()->with('translations')->firstOrFail();
    $slug = $post->translations->firstWhere('locale.value', 'it')?->slug ?? $post->translations->first()->slug;
    Carbon\Carbon::setTestNow(Carbon\Carbon::parse('2026-10-03 07:47:18.500', 'UTC'));
    try {
        $post->update(['status' => ContentStatus::PUBLISHED, 'publishedAt' => Carbon\Carbon::parse('2026-10-03 07:47:18.301', 'UTC')]);
        TagCache::invalidate(CacheTags::NEWS);
        $this->get('/it/news/'.$slug)->assertOk();
        expect($this->get('/it/news')->getContent())->toContain('/it/news/'.$slug.'"');
    } finally {
        Carbon\Carbon::setTestNow();
    }
});

it('sets no cookies for anonymous visitors on public pages (no session, like the reference)', function () {
    foreach (['/', '/it', '/en', '/it/news', '/it/projects', '/it/events', '/it/gallery', '/it/contatti', '/it/donate', '/it/newsletter', '/it/does-not-exist', '/sitemap.xml', '/robots.txt'] as $url) {
        expect($this->get($url)->headers->getCookies())->toBe([], $url);
    }
    expect($this->get('/admin/login')->headers->getCookies())->not->toBe([]);
});

it('renders public 5xx inside the site chrome, and a self-contained page when the chrome cannot render', function () {
    config(['app.debug' => false]);
    // A throwing public route, placed before the /{locale}/{path} catch-all.
    $router = app('router');
    $existing = $router->getRoutes()->getRoutes();
    $routes = new RouteCollection;
    $routes->add((new Route(['GET', 'HEAD'], 'it/__boom', fn () => throw new RuntimeException('secret-internal-detail')))->middleware('site')->setRouter($router)->setContainer(app()));
    foreach ($existing as $route) {
        $routes->add($route);
    }
    $router->setRoutes($routes);
    $res = $this->get('/it/__boom')->assertStatus(500);
    expect($res->getContent())->toContain(t('errors.errorTitle'))->toContain('<header')->not->toContain('secret-internal-detail');

    // Database unreachable: the chrome needs settings/menus, so the standalone errors/500 page is used.
    // Uses a separate connection name so the test's own transaction (RefreshDatabase) stays intact.
    config(['database.connections.broken' => array_merge(config('database.connections.mysql'), ['port' => 1]), 'database.default' => 'broken']);
    try {
        $res = $this->get('/it/__boom')->assertStatus(500);
        expect($res->getContent())->toContain('Qualcosa è andato storto')->not->toContain('<header')->not->toContain('SQLSTATE');
    } finally {
        config(['database.default' => 'mysql']);
        DB::purge('broken');
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql');

it('serves robots.txt and a sitemap with hreflang alternates', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin', false)->assertSee('Sitemap: ', false);
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('<loc>'.rtrim((string) config('sakila.site_url'), '/').'/it/projects</loc>')
        ->toContain('hreflang="en"')
        ->toContain('/it/chi-siamo');
});

it('exports upcoming events as iCalendar', function () {
    $html = $this->get('/it/events')->getContent();
    preg_match('#href="/it/events/([a-z0-9-]+)"#', $html, $m);
    expect($m)->not->toBeEmpty();
    $res = $this->get('/it/events/'.$m[1].'/ics')->assertOk();
    expect($res->headers->get('Content-Type'))->toContain('text/calendar');
    expect($res->getContent())->toContain('BEGIN:VEVENT');
});

it('thank-you page never trusts the URL: unknown references show a neutral message', function () {
    $this->get('/it/donate/thank-you?ref=SKL-2026-AAAAAAAA')->assertOk()->assertSee('data-status="UNKNOWN"', false);
});

it('image optimizer only accepts configured widths/qualities and allow-listed sources', function () {
    $this->get('/_img?url='.rawurlencode('https://evil.example/a.jpg').'&w=640&q=75')->assertNotFound();
    $this->get('/_img?url='.rawurlencode('https://upload.wikimedia.org/wikipedia/commons/a.jpg').'&w=641&q=75')->assertStatus(400);
    $this->get('/_img?url='.rawurlencode('/uploads/../.env').'&w=640&q=75')->assertNotFound();
});
