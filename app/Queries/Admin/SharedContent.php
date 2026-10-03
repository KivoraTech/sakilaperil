<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\I18n\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Helpers shared by the content admin queries (port of lib/queries/admin/shared-content.ts). */
final class SharedContent
{
    /**
     * `translations: { some: { title: { contains: q } } }` (MySQL collation is case-insensitive).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function titleSearch(Builder $query, string $q): Builder
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return $query->whereHas('translations', fn (Builder $t) => $t->where('title', 'like', $like));
    }

    /**
     * pickTranslation(): exact locale, else Italian marked as fallback, else null.
     *
     * @template T of Model
     *
     * @param  iterable<T>  $translations
     * @return array{row: T, isFallback: bool}|null
     */
    public static function pickTranslation(iterable $translations, string $locale): ?array
    {
        $fallback = null;
        foreach ($translations as $t) {
            if (ContentCommon::val($t->getAttribute('locale')) === $locale) {
                return ['row' => $t, 'isFallback' => false];
            }
            if (ContentCommon::val($t->getAttribute('locale')) === Locales::DEFAULT) {
                $fallback = $t;
            }
        }

        return $fallback !== null ? ['row' => $fallback, 'isFallback' => true] : null;
    }
}
