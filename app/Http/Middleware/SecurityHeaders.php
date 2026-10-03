<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Settings\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Security headers on every response.
 *
 * - CSP: no inline scripts in production (the pages contain none: page data and JSON-LD are data
 *   blocks, everything executable is a built file). Analytics origins are allowed only while the
 *   matching ID is set in Settings; anything that renders analytics must use an external script.
 * - HSTS on HTTPS responses in production (one year; no includeSubDomains/preload: other
 *   sub-domains of the association's domain may not be HTTPS).
 * - Admin, preview and install pages are never indexed nor stored by the browser (Back after
 *   signing out must not show the last screen).
 * In local development Vite's dev server origin is allowed for scripts/HMR.
 */
final class SecurityHeaders
{
    public const HSTS = 'max-age=31536000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self)');
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::csp());
        }
        if (app()->isProduction() && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', self::HSTS);
        }
        $headers->remove('X-Powered-By');

        $path = '/'.ltrim($request->path(), '/');
        foreach (['/admin', '/preview', '/install'] as $private) {
            if ($path === $private || str_starts_with($path, $private.'/')) {
                $headers->set('X-Robots-Tag', 'noindex, nofollow');
                // File downloads (CSV exports) keep their own header.
                if (! $headers->has('Content-Disposition')) {
                    $headers->set('Cache-Control', 'private, no-store');
                }
            }
        }

        return $response;
    }

    public static function csp(): string
    {
        $dev = app()->environment('local') && is_file(public_path('hot'));
        $vite = $dev ? ' '.trim((string) file_get_contents(public_path('hot'))) : '';
        $viteWs = $dev ? ' ws: wss:' : '';
        [$analyticsScript, $analyticsConnect] = self::analyticsOrigins();

        return implode('; ', array_filter([
            "default-src 'self'",
            // Vite's dev client and React refresh are inline/eval scripts; the production build has none.
            "script-src 'self'".($dev ? " 'unsafe-inline' 'unsafe-eval'" : '').$vite.' https://challenges.cloudflare.com'.$analyticsScript,
            "style-src 'self' 'unsafe-inline'".$vite,
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:".$vite,
            "media-src 'self' blob: https:",
            "connect-src 'self' https://challenges.cloudflare.com".$analyticsConnect.$vite.$viteWs,
            "frame-src 'self' https://challenges.cloudflare.com https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com https://www.google.com https://maps.google.com https://www.openstreetmap.org",
            "form-action 'self' https://rc-epay.esewa.com.np https://epay.esewa.com.np",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            app()->isProduction() && str_starts_with((string) config('app.url'), 'https://') ? 'upgrade-insecure-requests' : null,
        ]));
    }

    /**
     * Third-party analytics origins, only for the services switched on in Settings → analytics.
     *
     * @return array{0: string, 1: string} [script-src additions, connect-src additions]
     */
    private static function analyticsOrigins(): array
    {
        try {
            $analytics = (array) (app(SiteSettings::class)->get()['analytics'] ?? []);
        } catch (Throwable) {
            return ['', ''];
        }
        $script = $connect = '';
        if (! empty($analytics['plausibleDomain'])) {
            $script .= ' https://plausible.io';
            $connect .= ' https://plausible.io';
        }
        if (! empty($analytics['gaMeasurementId'])) {
            $script .= ' https://www.googletagmanager.com';
            $connect .= ' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com';
        }

        return [$script, $connect];
    }
}
