<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\ProjectStage;
use App\Enums\Role;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\NewsPost;
use App\Models\PageTranslation;
use App\Models\Project;
use App\Models\User;
use App\Settings\SiteSettings;
use App\Storage\Storage;
use App\View\Analytics;
use App\View\Seo;
use App\View\Ui;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

/*
| Regression tests for the public-site findings of the production audit (docs/audit):
| structured data, titles, share images, redirects, the image optimizer, planned projects,
| per-locale menu links, list layouts and the class-conflict trap of Html::cx().
*/

beforeEach(function () {
    config(['sakila.public_cache' => false]);
    $this->seed(DatabaseSeeder::class);
});

/** @return list<array<string, mixed>> */
function auditJsonLd(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

    return array_map(fn (string $j): array => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
}

function auditFirstSlug(string $pattern, string $html): string
{
    preg_match($pattern, $html, $m);
    expect($m)->not->toBeEmpty();

    return $m[1];
}

it('emits valid JSON-LD with @context on every page type (SEO-01)', function () {
    $news = auditFirstSlug('#href="/it/news/([a-z0-9-]+)"#', $this->get('/it/news')->getContent());
    $event = (string) Event::query()->with('translations')->firstOrFail()->translations->firstWhere('locale.value', 'it')->slug;
    $project = auditFirstSlug('#href="/it/projects/([a-z0-9-]+)"#', $this->get('/it/projects')->getContent());
    $types = [];
    foreach (['/it', '/it/chi-siamo', "/it/news/{$news}", "/it/events/{$event}", "/it/projects/{$project}"] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->not->toContain('__contextArgs')->not->toContain('<?php');
        foreach (auditJsonLd($html) as $node) {
            expect($node['@context'] ?? null)->toBe('https://schema.org');
            $types[$node['@type']] = true;
        }
    }
    expect(array_keys($types))->toContain('NGO', 'WebSite', 'NewsArticle', 'Event', 'BreadcrumbList');
    // The Blade compiler turns a literal '@context' into its own directive: keep JSON-LD arrays in PHP classes.
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if ($file->isFile()) {
            expect(str_contains((string) file_get_contents($file->getPathname()), "'@context'"))->toBeFalse($file->getPathname());
        }
    }
    expect(Seo::faqJsonLd([['question' => 'Q', 'answer' => 'A']])['mainEntity'][0]['acceptedAnswer']['text'])->toBe('A');
});

it('news structured data points to an existing logo and never names an admin account (SEO-04, UX-07)', function () {
    $post = NewsPost::query()->with('translations')->where('status', ContentStatus::PUBLISHED->value)->firstOrFail();
    $slug = (string) $post->translations->firstWhere('locale.value', 'it')->slug;
    $admin = User::query()->where('role', Role::SUPER_ADMIN->value)->firstOrFail();
    $post->update(['authorId' => $admin->id]);
    $html = $this->get("/it/news/{$slug}")->assertOk()->getContent();
    $article = collect(auditJsonLd($html))->firstWhere('@type', 'NewsArticle');
    expect($article['publisher']['logo']['url'])->not->toContain('logo-mark.svg')
        ->and($article['author']['@type'])->toBe('NGO')
        ->and($html)->not->toContain((string) $admin->name);

    $editor = User::query()->where('role', Role::EDITOR->value)->firstOrFail();
    $post->update(['authorId' => $editor->id]);
    $html = $this->get("/it/news/{$slug}")->getContent();
    expect($html)->toContain(e((string) $editor->name));
});

it('applies the title pattern to every page except the home page (SEO-02)', function () {
    $org = (string) app(SiteSettings::class)->get()['orgName'];
    $title = fn (string $url): string => html_entity_decode(auditFirstSlug('#<title>(.*?)</title>#s', $this->get($url)->getContent()));
    expect($title('/it/projects'))->toEndWith('· '.$org)
        ->and($title('/it/contatti'))->toEndWith('· '.$org)
        ->and(substr_count($title('/it'), $org))->toBe(1);
    // og:title keeps the plain title.
    expect($this->get('/it/projects')->getContent())->toMatch('#<meta property="og:title" content="(?!.*· )[^"]+">#');
    expect(Seo::clip(str_repeat('parola ', 40)))->toEndWith('…')->and(mb_strlen(Seo::clip(str_repeat('parola ', 40))))->toBeLessThanOrEqual(158);
});

it('gives every public page a share image and the static icons (SEO-05, SEO-06)', function () {
    foreach (['/it', '/it/projects', '/it/news', '/it/events', '/it/gallery', '/it/donate'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        expect($html)->toContain('property="og:image"')->toContain('name="twitter:card" content="summary_large_image"');
    }
    $html = $this->get('/it')->getContent();
    expect($html)->toContain('href="/favicon.ico"')->toContain('href="/apple-touch-icon.png"')->toContain('href="/site.webmanifest"');
    foreach (['favicon.ico', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest', 'brand/logo-mark.png'] as $file) {
        expect(is_file(public_path($file)))->toBeTrue($file);
    }
    expect(json_decode((string) file_get_contents(public_path('site.webmanifest')), true)['icons'])->toHaveCount(2);
});

it('uses permanent redirects and answers unknown paths with a 404 in one hop (SEO-07, SEO-17)', function () {
    $this->get('/')->assertStatus(301)->assertRedirect('/it');
    $this->get('/en/chi-siamo')->assertStatus(301)->assertRedirect('/en/about-us');
    $this->get('/projects')->assertStatus(301)->assertRedirect('/it/projects');
    $this->get('/chi-siamo')->assertStatus(301)->assertRedirect('/it/chi-siamo');
    $this->get('/does-not-exist')->assertNotFound();
    $this->get('/projects/does-not-exist')->assertNotFound();
    $this->get('/brand/logo-mark.svg')->assertNotFound();
    $this->get('/it/news?page=999')->assertNotFound();
    $this->get('/it/projects?page=999')->assertNotFound();
    $this->get('/it/news?page=1')->assertOk();
});

it('keeps robots.txt, the sitemap and the newsletter page consistent (SEO-12, SEO-13, SEO-15)', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();
    expect($robots)->toContain('Disallow: /install')->not->toContain('Host:')->not->toContain('thank-you');
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->not->toContain('<changefreq>')->not->toContain('<priority>')->not->toContain('/newsletter');
    expect($xml)->toMatch('#<loc>[^<]+/it/news</loc>\s*(<xhtml:link[^>]+/>\s*)+<lastmod>#');
    expect($this->get('/it/newsletter')->getContent())->toContain('<meta name="robots" content="noindex, follow">');
});

it('serves one cached variant per image however the URL is spelled, and limits new variants (SEC-01)', function () {
    $uploads = storage_path('framework/testing/audit-uploads');
    $cache = storage_path('framework/testing/audit-img-cache');
    foreach ([$uploads, $cache] as $dir) {
        if (is_dir($dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
        }
    }
    config(['sakila.local_uploads_dir' => $uploads, 'sakila.img_cache_dir' => $cache]);
    Storage::reset();
    $img = imagecreatetruecolor(200, 100);
    ob_start();
    imagepng($img);
    $key = '2026/10/'.str_repeat('a', 32).'.png';
    Storage::adapter()->put($key, (string) ob_get_clean(), 'image/png');
    $count = fn (): int => count(array_filter(iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS))), fn ($f) => $f->isFile()));

    $this->get('/_img?url='.rawurlencode('/uploads/'.$key).'&w=64&q=75')->assertOk();
    expect($count())->toBe(1);
    // Another spelling of the same file (one character still percent-encoded after decoding) is refused.
    $this->get('/_img?url='.rawurlencode('/uploads/2026/10/%61'.str_repeat('a', 31).'.png').'&w=64&q=75')->assertNotFound();
    $this->get('/_img?url='.rawurlencode('/uploads//'.$key).'&w=64&q=75')->assertNotFound();
    $this->get('/_img?url='.rawurlencode('/uploads/'.$key).'&w=64&q=75')->assertOk();
    expect($count())->toBe(1);

    // Cache hits are never limited; new variants are, per visitor.
    $limiter = 'img-miss:'.sha1('127.0.0.1');
    foreach (range(1, 120) as $i) {
        RateLimiter::hit($limiter, 60);
    }
    $this->get('/_img?url='.rawurlencode('/uploads/'.$key).'&w=64&q=75')->assertOk();
    $this->get('/_img?url='.rawurlencode('/uploads/'.$key).'&w=128&q=75')->assertStatus(429)->assertHeader('Retry-After');
    RateLimiter::clear($limiter);
});

it('is not an open proxy: only remote images from the media library are fetched (SEC-02)', function () {
    config(['sakila.img_cache_dir' => storage_path('framework/testing/audit-img-cache-remote-'.bin2hex(random_bytes(3)))]);
    $png = (function (): string {
        ob_start();
        imagepng(imagecreatetruecolor(10, 10));

        return (string) ob_get_clean();
    })();
    Http::fake(['*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    $this->get('/_img?url='.rawurlencode('https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg').'&w=640&q=75')->assertNotFound();
    Http::assertNothingSent();

    $asset = MediaAsset::query()->where('url', 'like', 'https://upload.wikimedia.org/%')->firstOrFail();
    $this->get('/_img?url='.rawurlencode((string) $asset->url).'&w=640&q=75')->assertOk();
    Http::assertSentCount(1);
});

it('shows a planned project without an empty photo box and without asking for donations (UX-01)', function () {
    $project = Project::query()->with('translations')->where('status', ContentStatus::PUBLISHED->value)->firstOrFail();
    $slug = (string) $project->translations->firstWhere('locale.value', 'it')->slug;
    $this->get("/it/projects/{$slug}")->assertOk()->assertSee('/it/donate?project='.$slug, false);

    $project->update(['stage' => ProjectStage::PLANNED, 'coverImageId' => null]);
    $html = $this->get("/it/projects/{$slug}")->assertOk()->getContent();
    expect($html)->not->toContain('/it/donate?project='.$slug)->not->toContain('aspect-[16/10]')->toContain('lg:col-span-12');
    $list = $this->get('/it/projects')->getContent();
    expect($list)->not->toContain('/it/donate?project='.$slug);
});

it('lists project and event facts as valid description lists (A11Y-01)', function () {
    $project = auditFirstSlug('#href="/it/projects/([a-z0-9-]+)"#', $this->get('/it/projects')->getContent());
    $event = (string) Event::query()->with('translations')->firstOrFail()->translations->firstWhere('locale.value', 'it')->slug;
    foreach (["/it/projects/{$project}", "/it/events/{$event}"] as $url) {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('#<dl[^>]*>(.*?)</dl>#s', $html, $lists);
        expect($lists[1])->not->toBeEmpty();
        foreach ($lists[1] as $dl) {
            // Every group is <div><dt>…</dt><dd>…</dd></div> (or bare dt/dd): nothing between the div and its dt.
            expect($dl)->not->toMatch('#<div[^>]*>\s*(?!<dt)<#');
        }
    }
});

it('drops menu links to a page that is not published in that language (QA-06)', function () {
    $item = NavigationItem::query()->whereNotNull('pageId')->where('isVisible', true)->get()
        ->first(fn ($i) => PageTranslation::query()->where('pageId', $i->pageId)->whereNotNull('publishedRevisionId')->count() === 2);
    expect($item)->not->toBeNull();
    $it = PageTranslation::query()->where('pageId', $item->pageId)->where('locale', 'it')->firstOrFail();
    $en = PageTranslation::query()->where('pageId', $item->pageId)->where('locale', 'en')->firstOrFail();
    $header = fn (string $url): string => auditFirstSlug('#(<header id="site-header".*?</header>)#s', $this->get($url)->getContent());
    expect($header('/it'))->toContain('href="/it/'.$it->slug.'"');

    $it->update(['publishedRevisionId' => null]);
    expect($header('/it'))->not->toContain('href="/it/'.$it->slug.'"')->not->toContain('href="/it/'.$en->slug.'"');
    expect($header('/en'))->toContain('href="/en/'.$en->slug.'"');
});

it('opens the past events when nothing is upcoming, and sizes short card lists (UX-04, UX-09)', function () {
    Event::query()->update(['startsAt' => now('UTC')->subMonths(2), 'endsAt' => now('UTC')->subMonths(2)->addHours(3)]);
    expect($this->get('/it/events')->assertOk()->getContent())->toMatch('#<details[^>]* open#');
    expect(Ui::cardGrid(1))->toBe('max-w-2xl')
        ->and(Ui::cardGrid(2))->not->toContain('lg:grid-cols-3')
        ->and(Ui::cardGrid(3))->toContain('lg:grid-cols-3');
});

it('does not stack two newsletter sign-ups at the end of a page (UX-06)', function () {
    expect($this->get('/it')->getContent())->not->toContain('id="newsletter-footer"');     // home ends with the newsletter block
    expect($this->get('/it/projects')->getContent())->toContain('id="newsletter-footer"'); // other pages keep the footer form
});

it('renders Plausible only in production when configured, and never Google Analytics (QA-09)', function () {
    expect((string) Analytics::headTags(['analytics' => ['plausibleDomain' => 'example.org']]))->toBe('');
    app()->detectEnvironment(fn () => 'production');
    try {
        expect((string) Analytics::headTags(['analytics' => ['plausibleDomain' => 'Example.org', 'gaMeasurementId' => 'G-ABCD1234']]))
            ->toBe('<script defer data-domain="example.org" src="https://plausible.io/js/script.js"></script>');
        expect((string) Analytics::headTags(['analytics' => ['plausibleDomain' => '"><script>alert(1)</script>']]))->toBe('');
        expect((string) Analytics::headTags(['analytics' => ['gaMeasurementId' => 'G-ABCD1234']]))->toBe('');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

it('never emits two conflicting layout utilities on one element (CODE-02)', function () {
    // Html::cx() joins classes, it does not merge them like tailwind-merge: when a base class and an
    // override of the same utility family both reach the HTML, CSS order decides (the donateCta and
    // ridge bugs). Scan the rendered pages for such pairs.
    $families = 'grid-cols|col-span|col-start|row-span|order|max-w|min-w|min-h|w|h|aspect|justify|items|gap-x|gap-y|gap';
    $news = auditFirstSlug('#href="/it/news/([a-z0-9-]+)"#', $this->get('/it/news')->getContent());
    $project = auditFirstSlug('#href="/it/projects/([a-z0-9-]+)"#', $this->get('/it/projects')->getContent());
    $urls = ['/it', '/en', '/it/chi-siamo', '/it/coinvolgiti', '/it/trasparenza', '/it/contatti', '/it/projects', "/it/projects/{$project}",
        '/it/news', "/it/news/{$news}", '/it/events', '/it/gallery', '/it/donate', '/it/donate/thank-you', '/it/newsletter', '/it/nope'];
    $conflicts = [];
    foreach ($urls as $url) {
        preg_match_all('/class="([^"]*)"/', $this->get($url)->getContent(), $m);
        foreach (array_unique($m[1]) as $classList) {
            $seen = [];
            foreach (preg_split('/\s+/', trim($classList)) ?: [] as $class) {
                if (str_ends_with($class, '!') || preg_match('/^((?:[^\s:]+:)*)-?('.$families.')-(.+)$/', $class, $c) !== 1) {
                    continue;
                }
                $seen[$c[1].'|'.$c[2]][$class] = true;
            }
            foreach ($seen as $group) {
                if (count($group) > 1) {
                    $conflicts[implode(' + ', array_keys($group))] = $url;
                }
            }
        }
    }
    expect($conflicts)->toBe([]);
});

it('keeps the donate page honest when only bank transfer is available (UX-02)', function () {
    $html = $this->get('/it/donate')->assertOk()->getContent();
    expect($html)->not->toContain(t('donatePage.badgeSecure'))
        ->not->toContain(t('donatePage.badgeBank'))
        ->toContain(e(t('donatePage.why2Bank')))
        ->not->toContain('id="bonifico"')
        ->toContain(e(t('donatePage.leadBank')));
    // The "why give with us" panel names no payment provider that is not set up.
    $why = auditFirstSlug('#(<aside[^>]*aria-labelledby="why-title".*?</aside>)#s', $html);
    expect($why)->not->toContain('Stripe')->not->toContain('PayPal');
    expect(substr_count($html, 'id="bank-transfer-title'))->toBeLessThanOrEqual(1);
    // Heading order: the widget has its own (visually hidden) h2, so no h1 → h3 jump.
    preg_match_all('/<h([1-6])[\s>]/', $html, $h);
    $levels = array_map('intval', $h[1]);
    foreach ($levels as $i => $level) {
        expect($i === 0 || $level <= $levels[$i - 1] + 1)->toBeTrue('heading skip at #'.$i);
    }
});
