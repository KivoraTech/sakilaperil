<?php

declare(strict_types=1);

use App\I18n\Locales;
use App\I18n\Routes;
use App\I18n\Translator;

if (! function_exists('t')) {
    /**
     * next-intl style message: t('donate.title'), t('projects.count', ['count' => 3]).
     *
     * @param  array<string, mixed>  $params
     */
    function t(string $key, array $params = [], ?string $locale = null): string
    {
        return Translator::t($key, $params, $locale);
    }
}

if (! function_exists('lurl')) {
    /** Locale-less internal href → /{locale}/… (like next-intl's <Link href>). */
    function lurl(string $href, ?string $locale = null): string
    {
        return Routes::localize($href, $locale);
    }
}

if (! function_exists('pick_localized')) {
    /** LocalizedString JSON → string for the current locale with Italian fallback. */
    function pick_localized(mixed $value, ?string $locale = null): string
    {
        return Locales::pick($value, $locale);
    }
}
