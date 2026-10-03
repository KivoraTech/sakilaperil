<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureProductionConfig;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use App\Settings\SiteSettings;
use App\Support\ProductionConfig;
use App\Support\RequestInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/*
| Regression tests for the 2026-10 production audit (platform): trusted proxies (CODE-01, SEC-04),
| security headers (SEC-03, SEC-05, QA-16), production settings validation (ENV-02), asset URLs
| (SEC-08) and the permission check of every admin action (CODE-06).
*/

/** Runs the request through the trusted-proxy middleware and returns what the app then sees. */
function throughProxyMiddleware(array $server): array
{
    $request = Request::create('http://sakila.test/it', 'GET', server: $server);

    return (new TrustProxies)->handle($request, fn (Request $r): array => [
        'ip' => $r->ip(), 'secure' => $r->isSecure(), 'host' => $r->getHost(),
    ]);
}

const FORWARDED = [
    'REMOTE_ADDR' => '203.0.113.9',
    'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 198.51.100.7',
    'HTTP_X_FORWARDED_PROTO' => 'https',
    'HTTP_X_FORWARDED_HOST' => 'evil.example',
    'HTTP_CF_CONNECTING_IP' => '9.9.9.9',
    'HTTP_X_REAL_IP' => '8.8.8.8',
];

it('ignores every forwarded header when TRUST_PROXY is off', function () {
    config(['sakila.trust_proxy' => false]);
    expect(throughProxyMiddleware(FORWARDED))->toBe(['ip' => '203.0.113.9', 'secure' => false, 'host' => 'sakila.test']);
});

it('reads the proxy settings from config, so it also works with a cached config', function () {
    // bootstrap/app.php used env('TRUST_PROXY'), which is null once `config:cache` has run.
    expect(file_get_contents(base_path('bootstrap/app.php')))->not->toContain("env('TRUST_PROXY'");
    config(['sakila.trust_proxy' => 'true', 'sakila.trusted_proxies' => '*']);
    expect(throughProxyMiddleware(FORWARDED)['secure'])->toBeTrue();
});

it('takes the right-most forwarded address from a trusted proxy, never the left-most one', function () {
    config(['sakila.trust_proxy' => true, 'sakila.trusted_proxies' => '203.0.113.9']);
    $seen = throughProxyMiddleware(FORWARDED);
    expect($seen['ip'])->toBe('198.51.100.7')   // appended by the proxy
        ->and($seen['ip'])->not->toBe('1.2.3.4') // sent by the client
        ->and($seen['secure'])->toBeTrue()
        ->and($seen['host'])->toBe('sakila.test'); // X-Forwarded-Host is never trusted
});

it('does not trust forwarded headers from an address that is not a listed proxy', function () {
    config(['sakila.trust_proxy' => true, 'sakila.trusted_proxies' => 'cloudflare']);
    expect(throughProxyMiddleware(FORWARDED))->toBe(['ip' => '203.0.113.9', 'secure' => false, 'host' => 'sakila.test']);
    // …and does trust a Cloudflare edge address.
    $viaCloudflare = throughProxyMiddleware(['REMOTE_ADDR' => '173.245.48.10'] + FORWARDED);
    expect($viaCloudflare['ip'])->toBe('198.51.100.7')->and($viaCloudflare['secure'])->toBeTrue();
});

it('uses the same resolved address for rate limits and the audit log', function () {
    config(['sakila.trust_proxy' => true, 'sakila.trusted_proxies' => '127.0.0.1']);
    Route::get('/__audit-probe/ip', fn () => RequestInfo::clientIp())->middleware('site');
    $this->get('/__audit-probe/ip', ['X-Forwarded-For' => '6.6.6.6, 198.51.100.7', 'CF-Connecting-IP' => '9.9.9.9'])
        ->assertOk()->assertSee('198.51.100.7');
    config(['sakila.trust_proxy' => false]);
    $this->get('/__audit-probe/ip', ['X-Forwarded-For' => '6.6.6.6', 'CF-Connecting-IP' => '9.9.9.9'])->assertSee('127.0.0.1');
});

it('sends HSTS only for HTTPS responses of the live site', function () {
    $headersFor = fn (string $url): ResponseHeaderBag => (new SecurityHeaders)
        ->handle(Request::create($url), fn () => response('ok'))->headers;
    expect($headersFor('https://sakila.test/it')->has('Strict-Transport-Security'))->toBeFalse(); // not production

    $this->app->detectEnvironment(fn () => 'production');
    expect($headersFor('https://sakila.test/it')->get('Strict-Transport-Security'))->toBe('max-age=31536000')
        ->and($headersFor('http://sakila.test/it')->has('Strict-Transport-Security'))->toBeFalse();
});

it('forbids inline scripts and lists analytics origins only when analytics is configured', function () {
    $scriptSrc = function (): string {
        preg_match('/script-src ([^;]+)/', SecurityHeaders::csp(), $m);

        return $m[1];
    };
    expect($scriptSrc())->not->toContain('unsafe-inline')->not->toContain('unsafe-eval')
        ->not->toContain('plausible.io')->not->toContain('googletagmanager')
        ->and(SecurityHeaders::csp())->toContain("style-src 'self' 'unsafe-inline'")->toContain("object-src 'none'");

    $this->app->instance(SiteSettings::class, new class
    {
        /** @return array<string, mixed> */
        public function get(): array
        {
            return ['analytics' => ['plausibleDomain' => 'sakila.example', 'gaMeasurementId' => 'G-ABCD1234']];
        }
    });
    expect($scriptSrc())->toContain('https://plausible.io')->toContain('https://www.googletagmanager.com')
        ->not->toContain('unsafe-inline');
});

it('never lets the browser store admin, preview or install pages', function () {
    $this->get('/admin/login')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    expect($this->get('/admin/login')->headers->get('Cache-Control'))->toContain('no-store');
    $public = $this->get('/api/health')->headers;
    expect((string) $public->get('X-Robots-Tag'))->not->toContain('noindex');
});

it('reports broken production settings by name and blocks the site with a 503', function () {
    $uploads = storage_path('framework/testing/audit-uploads');
    @mkdir($uploads, 0775, true);
    config([
        'app.debug' => false, 'app.url' => 'https://www.sakila.test', 'sakila.site_url' => 'https://www.sakila.test',
        'sakila.storage_driver' => 'local', 'sakila.local_uploads_dir' => $uploads,
    ]);
    expect(ProductionConfig::errors())->toBe([]);

    config(['sakila.site_url' => '', 'sakila.local_uploads_dir' => 'relative/uploads', 'app.debug' => true]);
    $errors = implode("\n", ProductionConfig::errors());
    expect($errors)->toContain('SITE_URL is not set')->toContain('LOCAL_UPLOADS_DIR must be an absolute path')->toContain('APP_DEBUG must be false');

    config(['sakila.site_url' => 'http://www.sakila.test', 'app.debug' => false, 'sakila.local_uploads_dir' => $uploads]);
    expect(ProductionConfig::errors())->toBe(['SITE_URL must start with https://']);
    config(['sakila.site_url' => 'https://<your-domain>']);
    expect(ProductionConfig::errors()[0])->toStartWith('SITE_URL is not set');

    // The middleware only acts in production, and names settings without showing their values.
    $handle = fn () => (new EnsureProductionConfig)->handle(Request::create('/it'), fn () => response('page'));
    expect($handle()->getContent())->toBe('page');
    $this->app->detectEnvironment(fn () => 'production');
    $blocked = $handle();
    expect($blocked->getStatusCode())->toBe(503)
        ->and($blocked->getContent())->toContain('SITE_URL')->not->toContain('your-domain');
    config(['sakila.site_url' => 'https://www.sakila.test']);
    expect($handle()->getContent())->toBe('page');
});

it('has no localhost fallback for the public address in production', function () {
    $config = (string) file_get_contents(config_path('sakila.php'));
    expect($config)->not->toContain("env('SITE_URL', env('APP_URL', 'http://localhost:8000'))");
});

it('builds asset URLs without the request host', function () {
    if (! is_file(public_path('build/manifest.json'))) {
        $this->markTestSkipped('needs `npm run build`');
    }
    expect(Vite::asset('resources/js/site.ts'))->toStartWith('/build/assets/');
    $html = $this->get('/admin/login', ['Host' => 'evil.example'])->getContent();
    expect($html)->not->toContain('evil.example/build');
});

it('checks a capability for every admin action, in the route or in the controller method', function () {
    $actions = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'admin/actions/'));
    expect($actions->count())->toBeGreaterThan(30);
    foreach ($actions as $route) {
        $middleware = $route->gatherMiddleware();
        expect($middleware)->toContain('admin.auth');
        if (collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'capability:'))) {
            continue;
        }
        // No `capability:` middleware: the controller method itself must check one.
        [$class, $method] = explode('@', $route->getActionName());
        $ref = new ReflectionMethod($class, $method);
        $lines = array_slice(file((string) $ref->getFileName()), $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1);
        expect(implode('', $lines))->toMatch('/requireCapability\(|Permissions::can\(|->authorize\(/', $route->uri().' has no capability check');
    }
});
