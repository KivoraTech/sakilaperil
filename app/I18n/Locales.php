<?php

declare(strict_types=1);

namespace App\I18n;

/** Port of sakila/lib/i18n/config.ts + localized.ts. */
final class Locales
{
    /** @var list<string> */
    public const ALL = ['it', 'en'];

    public const DEFAULT = 'it';

    /** @var array<string, string> */
    public const LABELS = ['it' => 'Italiano', 'en' => 'English'];

    public static function isLocale(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::ALL, true);
    }

    /** Current request locale (set by the SetLocale middleware), default "it". */
    public static function current(): string
    {
        $l = app()->getLocale();

        return self::isLocale($l) ? $l : self::DEFAULT;
    }

    /**
     * LocalizedString `{ it, en? }` → value for $locale, falling back to Italian, then any value.
     * Accepts unknown JSON (array, string, null) and never throws.
     */
    public static function pick(mixed $value, ?string $locale = null): string
    {
        $locale ??= self::current();
        if (is_string($value)) {
            return $value;
        }
        if (! is_array($value)) {
            return '';
        }
        foreach ([$locale, self::DEFAULT, ...self::ALL] as $l) {
            $v = $value[$l] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return $v;
            }
        }

        return '';
    }
}
