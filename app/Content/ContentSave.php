<?php

declare(strict_types=1);

namespace App\Content;

use App\RichText\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** Steps shared by the content save actions (news, projects, events, albums, reports). */
final class ContentSave
{
    /**
     * Locales to write: Italian always, English when enabled.
     *
     * @param  array{it: array<string, mixed>, en: array<string, mixed>}  $tr
     * @return list<array<string, mixed>>
     */
    public static function locales(array $tr): array
    {
        return [
            ['locale' => 'it', ...$tr['it']],
            ...(($tr['en']['enabled'] ?? false) ? [['locale' => 'en', ...$tr['en']]] : []),
        ];
    }

    /**
     * Sanitised bodies per locale (strict allow-list before saving).
     *
     * @param  list<array<string, mixed>>  $locales
     * @return list<array<string, mixed>>
     */
    public static function bodies(array $locales): array
    {
        return array_map(fn (array $l): array => RichText::sanitize($l['body'] ?? null), $locales);
    }

    /**
     * Asset ids referenced by image nodes of all bodies.
     *
     * @param  list<array<string, mixed>>  $bodies
     * @return list<string>
     */
    public static function bodyAssetIds(array $bodies): array
    {
        $ids = [];
        foreach ($bodies as $b) {
            foreach (RichText::collectAssetIds($b) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /** `dateOrNull(v.publishedAt) ?? (PUBLISHED ? existing ?? now : null)` */
    public static function publishedAt(string $value, string $status, ?Carbon $existing): ?Carbon
    {
        return ContentCommon::dateOrNull($value) ?? ($status === 'PUBLISHED' ? ($existing ?? Carbon::now()) : null);
    }

    /**
     * Upsert one translation row per locale, then drop the English row when English is disabled.
     *
     * @param  class-string<Model>  $class
     * @param  list<array{locale: string, fields: array<string, mixed>}>  $rows
     */
    public static function upsertTranslations(string $class, string $fk, string $parentId, array $rows, bool $enEnabled): void
    {
        foreach ($rows as $row) {
            $class::query()->updateOrCreate([$fk => $parentId, 'locale' => $row['locale']], $row['fields']);
        }
        if (! $enEnabled) {
            $class::query()->where($fk, $parentId)->where('locale', 'en')->delete();
        }
    }
}
