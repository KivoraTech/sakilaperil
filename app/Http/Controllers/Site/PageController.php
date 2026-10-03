<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Locales;
use App\I18n\Routes;
use App\Queries\Public\Pages;
use App\View\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/** app/[locale]/(site)/page.tsx (home) and [...slug]/page.tsx (CMS pages built with blocks). */
final class PageController extends SiteController
{
    public function home(): Response
    {
        $locale = $this->locale();
        $page = Pages::byKey($locale, 'home');
        if ($page === null) {
            return $this->notFound();
        }
        $alternates = ['it' => '/', 'en' => '/'];
        $meta = $this->meta($alternates, $page['seoTitle'] ?? null, $page['seoDescription'] ?? null, $this->ogImage($page['ogImageId'] ?? null), noIndex: (bool) $page['noIndex']);
        $meta['preloadItalic'] = true; // the hero headline accent is italic and above the fold

        return response()->view('site.pages.home', [
            'locale' => $locale,
            'page' => $page,
            'alternates' => $alternates,
            'meta' => $meta,
        ]);
    }

    public function show(string $locale, string $path): Response|RedirectResponse
    {
        $slug = $this->pageSlug($path);
        if ($slug === null) {
            return $this->notFound();
        }
        $page = Pages::bySlug($locale, $slug);
        if ($page === null) {
            // The slug may belong to another locale (e.g. /en/chi-siamo after switching language):
            // send the visitor to this locale's version, or to the Italian page if untranslated.
            foreach (Locales::ALL as $other) {
                if ($other === $locale) {
                    continue;
                }
                $alt = Pages::bySlug($other, $slug);
                if ($alt !== null) {
                    if (! empty($alt['alternates'][$locale])) {
                        return redirect(Seo::localePath($locale, $alt['alternates'][$locale]), 301);
                    }
                    if ($other === Locales::DEFAULT) {
                        return redirect(Seo::localePath(Locales::DEFAULT, '/'.$slug), 301);
                    }
                }
            }

            return $this->notFound();
        }

        return response()->view('site.pages.page', [
            'locale' => $locale,
            'page' => $page,
            'alternates' => $page['alternates'],
            'meta' => $this->meta($page['alternates'], $page['seoTitle'] ?? $page['title'], $page['seoDescription'] ?? null, $this->ogImage($page['ogImageId'] ?? null), noIndex: (bool) $page['noIndex']),
        ]);
    }

    /** Only single-segment, well-formed slugs can be pages; "home" lives at /{locale}. */
    private function pageSlug(string $path): ?string
    {
        if (str_contains($path, '/')) {
            return null;
        }

        return $path !== 'home' && preg_match(Routes::SLUG_PATTERN, $path) === 1 ? $path : null;
    }
}
