<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Locales;
use App\I18n\Routes;
use App\Queries\Public\Albums;
use App\Queries\Public\Events;
use App\Queries\Public\News;
use App\Queries\Public\Pages;
use App\Queries\Public\Projects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Unmatched URLs:
 * - /{it|en}/… → the site 404 inside the chrome;
 * - an unknown locale-like first segment (/fr, /de/…) → plain 404 (never redirected);
 * - a locale-less path that EXISTS in Italian (/projects, /chi-siamo, /news/<slug>) → 301 to /it/…;
 * - any other locale-less public path → the site 404 directly (no redirect-then-404 chain);
 * - app paths (/admin, /api, /preview, /uploads, /_img, /build…) and file-like paths → plain 404.
 */
final class FallbackController extends SiteController
{
    private const APP_PREFIXES = ['admin', 'api', 'preview', 'uploads', '_img', 'build', 'storage', 'up', 'install', 'index.php'];

    private const STATIC_PATHS = ['donate', 'donate/thank-you', 'donate/cancelled', 'newsletter'];

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $segments = $request->segments();
        $first = $segments[0] ?? '';
        if (Locales::isLocale($first)) {
            app()->setLocale($first);

            return $this->notFound();
        }
        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $first) === 1) {
            abort(404);
        }
        if (in_array($first, self::APP_PREFIXES, true) || ! $request->isMethod('GET') || str_contains($first, '.')) {
            abort(404);
        }
        if ($this->existsInDefaultLocale($segments)) {
            $qs = $request->getQueryString();

            return redirect('/'.Locales::DEFAULT.'/'.implode('/', array_map('rawurlencode', $segments)).($qs ? '?'.$qs : ''), 301);
        }
        app()->setLocale(Locales::DEFAULT);

        return $this->notFound();
    }

    /**
     * Does /it/<segments> lead somewhere (a list, a published item, a page in either language)?
     *
     * @param  list<string>  $segments
     */
    private function existsInDefaultLocale(array $segments): bool
    {
        $path = implode('/', $segments);
        $collections = array_map(fn (string $p): string => ltrim($p, '/'), array_values(Routes::COLLECTION_PATHS));
        if (in_array($path, self::STATIC_PATHS, true) || in_array($path, $collections, true)) {
            return true;
        }
        $locale = Locales::DEFAULT;
        try {
            if (count($segments) === 1) {
                foreach (Locales::ALL as $l) {
                    if (preg_match(Routes::SLUG_PATTERN, $segments[0]) === 1 && Pages::bySlug($l, $segments[0]) !== null) {
                        return true; // PageController sends other-locale slugs to the right URL
                    }
                }

                return false;
            }
            if (count($segments) === 2) {
                [$collection, $slug] = $segments;

                return match ('/'.$collection) {
                    Routes::COLLECTION_PATHS['projects'] => Projects::get($locale, $slug) !== null,
                    Routes::COLLECTION_PATHS['news'] => News::get($locale, $slug) !== null,
                    Routes::COLLECTION_PATHS['events'] => Events::get($locale, $slug) !== null,
                    Routes::COLLECTION_PATHS['gallery'] => Albums::get($locale, ['slug' => $slug]) !== null,
                    default => false,
                };
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
