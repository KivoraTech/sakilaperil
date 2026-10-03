<?php

declare(strict_types=1);

namespace App\Engagement\Forms;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Engagement\Spam\Turnstile;
use App\I18n\Locales;
use App\I18n\Routes;
use App\Settings\SettingsTodo;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;

/** Everything a public form needs from the server (port of lib/forms/context.ts). */
final class FormContext
{
    /**
     * Href of the published "privacy" system page in this language. When the policy is not published
     * in this language, the link goes to the default-language policy (a consent link must never 404).
     */
    public static function privacyHref(string $locale): string
    {
        foreach (array_unique([$locale, Locales::DEFAULT]) as $candidate) {
            $slug = self::publishedPrivacySlug($candidate);
            if ($slug !== null) {
                return '/'.$candidate.Routes::page($slug);
            }
        }

        return '/'.$locale.Routes::page('privacy');
    }

    private static function publishedPrivacySlug(string $locale): ?string
    {
        $slug = TagCache::remember([CacheTags::PAGES], "engagement:privacy-slug:{$locale}", function () use ($locale) {
            $row = DB::table('page_translations as t')
                ->join('pages as p', 'p.id', '=', 't.pageId')
                ->where('p.key', 'privacy')
                ->where('p.status', 'PUBLISHED')
                ->where('t.locale', $locale)
                ->whereNotNull('t.publishedRevisionId')
                ->value('t.slug');

            return is_string($row) ? $row : null;
        });

        return is_string($slug) ? $slug : null;
    }

    /**
     * Editor messages (SiteSettings.formMessages), privacy link and Turnstile key.
     *
     * @param  'contact'|'volunteer'|'membership'|'partner'|'newsletter'  $kind
     * @return array{privacyHref: string, successMessage: ?string, intro: ?string, turnstileSiteKey: ?string}
     */
    public static function get(string $locale, string $kind): array
    {
        $settings = app(SiteSettings::class)->get();
        $messages = $settings['formMessages'][$kind] ?? [];
        $clean = static function (mixed $v) use ($locale): ?string {
            $s = Locales::pick($v, $locale);

            return $s !== '' && ! SettingsTodo::isTodo($s) ? $s : null;
        };

        return [
            'privacyHref' => self::privacyHref($locale),
            'successMessage' => $clean($messages['success'] ?? null),
            'intro' => $clean($messages['intro'] ?? null),
            'turnstileSiteKey' => Turnstile::siteKey(),
        ];
    }
}
