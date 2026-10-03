<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\ContentStatus;
use App\I18n\Routes;
use App\Models\Event;
use App\Models\GalleryAlbum;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Port of lib/queries/public/sitemap.ts: everything public and PUBLISHED, grouped per item so the
 * sitemap can emit hreflang alternates. Drafts, archived, future-dated and noIndex pages are excluded.
 */
final class Sitemap
{
    /**
     * @return list<array{paths: array<string, string>, lastModified: string|null}>
     */
    public static function items(): array
    {
        $tags = [CacheTags::PAGES, CacheTags::PROJECTS, CacheTags::NEWS, CacheTags::EVENTS, CacheTags::ALBUMS];

        return TagCache::remember($tags, 'sitemap-items', function (): array {
            $items = [];
            $push = function (Collection $trs, callable $toPath, $updated) use (&$items): void {
                if ($trs->isEmpty()) {
                    return;
                }
                $paths = [];
                foreach ($trs as $t) {
                    $paths[Shared::localeOf($t)] = $toPath((string) $t->slug);
                }
                $items[] = ['paths' => $paths, 'lastModified' => Shared::iso($updated)];
            };
            $pages = Page::query()->where('status', ContentStatus::PUBLISHED->value)
                ->with(['translations' => fn ($t) => $t->whereNotNull('publishedRevisionId')->where('noIndex', false)])
                ->get();
            foreach ($pages as $p) {
                $push($p->translations, Routes::page(...), $p->updatedAt);
            }
            foreach (Shared::published(Project::query())->with('translations')->get() as $row) {
                $push($row->translations, Routes::project(...), $row->updatedAt);
            }
            foreach (Shared::published(NewsPost::query())->with('translations')->get() as $row) {
                $push($row->translations, Routes::news(...), $row->updatedAt);
            }
            foreach (Shared::published(GalleryAlbum::query())->with('translations')->get() as $row) {
                $push($row->translations, Routes::album(...), $row->updatedAt);
            }
            foreach (Event::query()->where('status', ContentStatus::PUBLISHED->value)->with('translations')->get() as $row) {
                $push($row->translations, Routes::event(...), $row->updatedAt);
            }

            return $items;
        });
    }
}
