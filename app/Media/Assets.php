<?php

declare(strict_types=1);

namespace App\Media;

use App\I18n\Locales;
use App\Models\MediaAsset;

/**
 * Port of sakila/lib/queries/public/media.ts: resolve media assets for rendering in ONE query.
 * Unknown ids are simply absent. Result shape = ResolvedAsset of the reference.
 */
final class Assets
{
    /**
     * @param  iterable<string>  $ids
     * @return array<string, array{id: string, url: string, width: ?int, height: ?int, alt: string, mimeType: string, focalX: ?float, focalY: ?float}>
     */
    public static function byIds(iterable $ids, ?string $locale = null): array
    {
        $unique = array_values(array_unique(array_filter([...$ids], fn (string $id): bool => $id !== '')));
        if ($unique === []) {
            return [];
        }
        $out = [];
        foreach (MediaAsset::query()->whereIn('id', $unique)->get() as $row) {
            $dto = self::toDto($row, $locale);
            $out[$dto['id']] = $dto;
        }

        return $out;
    }

    /** @return array{id: string, url: string, width: ?int, height: ?int, alt: string, mimeType: string, focalX: ?float, focalY: ?float}|null */
    public static function toDto(?MediaAsset $row, ?string $locale = null): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'url' => (string) $row->url,
            'width' => $row->width !== null ? (int) $row->width : null,
            'height' => $row->height !== null ? (int) $row->height : null,
            'alt' => Locales::pick($row->alt, $locale),
            'mimeType' => (string) $row->mimeType,
            'focalX' => $row->focalX !== null ? (float) $row->focalX : null,
            'focalY' => $row->focalY !== null ? (float) $row->focalY : null,
        ];
    }
}
