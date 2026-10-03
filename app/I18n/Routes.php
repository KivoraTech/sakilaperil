<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * URL conventions (ADR-007), port of sakila/lib/i18n/routes.ts.
 * - Every public URL is locale-prefixed: /it/…, /en/…
 * - Collection segments are the same English words in both locales; item slugs are localized.
 * - Pages live at /{locale}/{page-slug}; the home page has slug "home" and is served at /{locale}.
 * - Stored internal links are LOCALE-LESS ("/projects", "/chi-siamo#team"); `self::localize()` adds the prefix.
 */
final class Routes
{
    /** @var array<string, string> */
    public const COLLECTION_PATHS = ['projects' => '/projects', 'news' => '/news', 'events' => '/events', 'gallery' => '/gallery'];

    /** @var list<string> */
    public const SYSTEM_PAGE_KEYS = ['home', 'about', 'get-involved', 'transparency', 'contact', 'privacy', 'cookies'];

    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @return list<string> */
    public static function routeKeys(): array
    {
        return [...array_keys(self::COLLECTION_PATHS), ...self::SYSTEM_PAGE_KEYS, 'donate'];
    }

    /**
     * Page slugs that would be shadowed by app routes. The page builder must reject them.
     * Same list as the reference plus "newsletter" (/{locale}/newsletter is an app route there too).
     *
     * @return list<string>
     */
    public static function reservedSlugs(): array
    {
        return [...array_keys(self::COLLECTION_PATHS), 'admin', 'api', 'preview', 'uploads', 'donate', 'sitemap.xml', 'robots.txt', '_next', 'newsletter'];
    }

    public static function slugify(string $input): string
    {
        $s = class_exists(\Normalizer::class) ? (string) \Normalizer::normalize($input, \Normalizer::FORM_KD) : self::stripAccents($input);
        $s = preg_replace('/[\x{0300}-\x{036f}]/u', '', $s) ?? '';
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        $s = trim($s, '-');

        return substr($s, 0, 80);
    }

    public static function page(string $slug): string
    {
        return $slug === 'home' ? '/' : '/'.$slug;
    }

    public static function project(string $slug): string
    {
        return self::COLLECTION_PATHS['projects'].'/'.$slug;
    }

    public static function news(string $slug): string
    {
        return self::COLLECTION_PATHS['news'].'/'.$slug;
    }

    public static function event(string $slug): string
    {
        return self::COLLECTION_PATHS['events'].'/'.$slug;
    }

    public static function album(string $slug): string
    {
        return self::COLLECTION_PATHS['gallery'].'/'.$slug;
    }

    /**
     * Locale-less internal href → locale-prefixed URL (the job of next-intl's <Link>).
     * External/https, mailto:, tel: and pure anchors are returned unchanged.
     */
    public static function localize(string $href, ?string $locale = null): string
    {
        $locale ??= Locales::current();
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(https?:|mailto:|tel:|//)#i', $href) === 1) {
            return $href;
        }
        if (! str_starts_with($href, '/')) {
            $href = '/'.$href;
        }
        if (preg_match('#^/(it|en)(/|$|\?|\#)#', $href) === 1) {
            return $href;
        }
        if ($href === '/') {
            return '/'.$locale;
        }
        if (str_starts_with($href, '/#') || str_starts_with($href, '/?')) {
            return '/'.$locale.substr($href, 1);
        }

        return '/'.$locale.$href;
    }

    /** Without intl: fold common Latin accents (only used when Normalizer is unavailable). */
    private static function stripAccents(string $s): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return $t !== false ? $t : $s;
    }
}
