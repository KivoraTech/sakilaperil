<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Enums\ContentStatus;
use App\I18n\Locales;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Helpers shared by public read queries (port of lib/queries/public/shared.ts).
 *
 * DRAFT SAFETY: every public query MUST filter status PUBLISHED (and a publishedAt in the past where
 * applicable). Pages read ONLY the published revision snapshot, never page_sections. Results are
 * plain arrays with ISO-8601 date strings so they can be cached by TagCache.
 */
final class Shared
{
    /**
     * `status = PUBLISHED AND (publishedAt IS NULL OR publishedAt <= now)`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $q
     * @return Builder<TModel>
     */
    public static function published(Builder $q): Builder
    {
        return $q->where('status', ContentStatus::PUBLISHED->value)
            ->where(fn (Builder $w) => $w->whereNull('publishedAt')->orWhere('publishedAt', '<=', Carbon::now('UTC')));
    }

    /**
     * Translations to fetch for a locale: the locale itself plus the default-locale fallback.
     *
     * @return list<string>
     */
    public static function translationLocales(string $locale): array
    {
        return $locale === Locales::DEFAULT ? [$locale] : [$locale, Locales::DEFAULT];
    }

    /**
     * Pick the requested translation or fall back to the default locale.
     *
     * @template T of object
     *
     * @param  iterable<T>  $translations
     * @return array{0: T, 1: bool}|null [translation, isFallback]
     */
    public static function pick(iterable $translations, string $locale): ?array
    {
        $fallback = null;
        foreach ($translations as $t) {
            $l = self::localeOf($t);
            if ($l === $locale) {
                return [$t, false];
            }
            if ($l === Locales::DEFAULT) {
                $fallback ??= $t;
            }
        }

        return $fallback !== null ? [$fallback, true] : null;
    }

    public static function localeOf(object $t): string
    {
        $l = $t->locale ?? null;

        return $l instanceof \BackedEnum ? (string) $l->value : (string) $l;
    }

    /** JS `Date#toISOString()` (UTC, milliseconds). */
    public static function iso(?DateTimeInterface $d): ?string
    {
        return $d === null ? null : Carbon::instance($d)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function enumValue(mixed $v): ?string
    {
        return $v instanceof \BackedEnum ? (string) $v->value : ($v === null ? null : (string) $v);
    }
}
