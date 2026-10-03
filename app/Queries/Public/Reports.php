<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Media\Assets;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * Port of lib/queries/public/reports.ts.
 * ReportDTO: {id, type, year, title, description, file{url,size,mimeType,filename}|null, coverImage, publishedAt, isFallback}
 */
final class Reports
{
    /**
     * @param  array{types?: list<string>}  $opts
     * @return list<array<string, mixed>>
     */
    public static function list(string $locale, array $opts = []): array
    {
        $key = 'reports:'.$locale.':'.md5((string) json_encode($opts));

        return TagCache::remember([CacheTags::REPORTS], $key, function () use ($locale, $opts): array {
            $types = $opts['types'] ?? [];
            $rows = Shared::published(Report::query())
                ->when($types !== [], fn (Builder $b) => $b->whereIn('type', $types))
                ->with([
                    'translations' => fn ($t) => $t->whereIn('locale', Shared::translationLocales($locale))->with('file'),
                    'file',
                    'coverImage',
                ])
                ->orderByDesc('year')
                ->orderBy('position')
                ->get();
            $out = [];
            foreach ($rows as $r) {
                $picked = Shared::pick($r->translations, $locale);
                if ($picked === null) {
                    continue;
                }
                [$t, $fallback] = $picked;
                $f = $t->file ?? $r->file;
                $out[] = [
                    'id' => (string) $r->id,
                    'type' => Shared::enumValue($r->type),
                    'year' => (int) $r->year,
                    'title' => (string) $t->title,
                    'description' => $t->description,
                    'file' => $f !== null ? ['url' => (string) $f->url, 'size' => (int) $f->size, 'mimeType' => (string) $f->mimeType, 'filename' => (string) $f->filename] : null,
                    'coverImage' => Assets::toDto($r->coverImage, $locale),
                    'publishedAt' => Shared::iso($r->publishedAt),
                    'isFallback' => $fallback,
                ];
            }

            return $out;
        });
    }
}
