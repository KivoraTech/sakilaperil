<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\HtmlString;

/**
 * Visitor statistics chosen in Admin → Settings → Google & sharing.
 *
 * Only Plausible is rendered: it sets no cookies and stores no personal data, so it needs no
 * consent banner and matches the site's cookie policy ("no tracking cookies"). A Google Analytics
 * (GA4) id can be stored in the settings but is deliberately NOT rendered: GA4 sets cookies and
 * would require a consent banner the site does not have.
 */
final class Analytics
{
    /** @param  array<string, mixed>  $settings */
    public static function plausibleDomain(array $settings): ?string
    {
        $domain = $settings['analytics']['plausibleDomain'] ?? null;
        if (! is_string($domain)) {
            return null;
        }
        $domain = strtolower(trim($domain));

        // A bare host name (optionally several, comma-separated, as Plausible allows).
        return preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?(,[a-z0-9]([a-z0-9.-]*[a-z0-9])?)*$/', $domain) === 1 ? $domain : null;
    }

    /** @param  array<string, mixed>  $settings */
    public static function headTags(array $settings): HtmlString
    {
        $domain = self::plausibleDomain($settings);
        if ($domain === null || ! app()->isProduction()) {
            return new HtmlString('');
        }

        return new HtmlString('<script defer data-domain="'.e($domain).'" src="https://plausible.io/js/script.js"></script>');
    }
}
