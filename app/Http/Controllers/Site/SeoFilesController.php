<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Locales;
use App\I18n\Routes;
use App\Queries\Public\Sitemap;
use App\View\Seo;
use Illuminate\Http\Response;

/** app/sitemap.ts + app/robots.ts. */
final class SeoFilesController
{
    public function robots(): Response
    {
        $base = Seo::siteUrl();
        // Result pages (thank-you, cancelled) are kept out with `noindex`, which crawlers only see if allowed in.
        $body = "User-Agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nDisallow: /preview\nDisallow: /install\n\nSitemap: {$base}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }

    /** Published content only (drafts, archived, future-dated and noIndex pages are excluded by the query). */
    public function sitemap(): Response
    {
        $base = Seo::siteUrl();
        $url = fn (string $l, string $p): string => $base.'/'.$l.($p === '/' ? '' : $p);
        $entries = [];
        $addAll = function (array $paths, array $extra = []) use (&$entries, $url): void {
            $languages = [];
            foreach ($paths as $l => $p) {
                $languages[$l] = $url($l, $p);
            }
            foreach ($paths as $l => $p) {
                $entries[] = ['url' => $url($l, $p), 'languages' => $languages, ...$extra];
            }
        };
        try {
            $items = Sitemap::items();
        } catch (\Throwable) {
            $items = [];
        }
        // A list page changed when its newest item did.
        $newest = [];
        foreach ($items as $it) {
            foreach (Routes::COLLECTION_PATHS as $prefix) {
                $first = (string) (array_values($it['paths'])[0] ?? '');
                if (str_starts_with($first, $prefix.'/') && ($it['lastModified'] ?? '') > ($newest[$prefix] ?? '')) {
                    $newest[$prefix] = $it['lastModified'];
                }
            }
        }
        foreach ([...array_values(Routes::COLLECTION_PATHS), '/donate'] as $p) {
            $addAll(array_fill_keys(Locales::ALL, $p), ['lastmod' => $newest[$p] ?? null]);
        }
        foreach ($items as $it) {
            $addAll($it['paths'], ['lastmod' => $it['lastModified']]);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";
        foreach ($entries as $e) {
            $xml .= "<url>\n<loc>".e($e['url'])."</loc>\n";
            foreach ($e['languages'] as $l => $href) {
                $xml .= '<xhtml:link rel="alternate" hreflang="'.e($l).'" href="'.e($href).'" />'."\n";
            }
            if (! empty($e['lastmod'])) {
                $xml .= '<lastmod>'.e($e['lastmod'])."</lastmod>\n";
            }
            $xml .= "</url>\n";
        }
        $xml .= "</urlset>\n";

        return response($xml, 200, ['Content-Type' => 'application/xml', 'Cache-Control' => 'public, max-age=3600']);
    }
}
