<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\GalleryItemType;
use App\I18n\Locales;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\GalleryAlbum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Port of lib/queries/public/albums.ts.
 * AlbumSummary: {id, slug, title, description, coverImage, itemCount, takenAt, isFallback};
 * AlbumDetail adds items (PHOTO {id,type,image,caption} | VIDEO {id,type,provider,videoId,caption}) + alternates.
 */
final class Albums
{
    /**
     * @param  array{limit?: int, projectId?: string}  $opts
     * @return list<array<string, mixed>>
     */
    public static function list(string $locale, array $opts = []): array
    {
        $key = 'albums:'.$locale.':'.md5((string) json_encode($opts));

        return TagCache::remember([CacheTags::ALBUMS], $key, function () use ($locale, $opts): array {
            $q = Shared::published(GalleryAlbum::query())
                ->when(! empty($opts['projectId']), fn (Builder $b) => $b->where('projectId', $opts['projectId']))
                ->with(['translations' => fn ($t) => $t->whereIn('locale', Shared::translationLocales($locale)), 'coverImage'])
                ->withCount('items')
                ->orderBy('position')
                ->orderByDesc('takenAt');
            if (isset($opts['limit'])) {
                $q->limit((int) $opts['limit']);
            }
            $out = [];
            foreach ($q->get() as $r) {
                $picked = Shared::pick($r->translations, $locale);
                if ($picked === null) {
                    continue;
                }
                [$t, $fallback] = $picked;
                $out[] = [
                    'id' => (string) $r->id,
                    'slug' => (string) $t->slug,
                    'title' => (string) $t->title,
                    'description' => $t->description,
                    'coverImage' => Assets::toDto($r->coverImage, $locale),
                    'itemCount' => (int) $r->items_count,
                    'takenAt' => Shared::iso($r->takenAt),
                    'isFallback' => $fallback,
                ];
            }

            return $out;
        });
    }

    /**
     * Album by slug, or by id (for the gallery block's `albumId`).
     *
     * @param  array{slug: string}|array{id: string}  $by
     * @return array<string, mixed>|null
     */
    public static function get(string $locale, array $by): ?array
    {
        $key = 'album:'.$locale.':'.md5((string) json_encode($by));

        return TagCache::remember([CacheTags::ALBUMS], $key, function () use ($locale, $by): ?array {
            $q = Shared::published(GalleryAlbum::query());
            if (isset($by['id'])) {
                $q->where('id', $by['id']);
            } else {
                $q->whereHas('translations', fn (Builder $t) => $t->where('slug', $by['slug'])->whereIn('locale', Shared::translationLocales($locale)));
            }
            $r = $q->with(['translations', 'coverImage', 'items' => fn ($i) => $i->orderBy('position'), 'items.mediaAsset'])->first();
            if ($r === null) {
                return null;
            }
            $picked = Shared::pick($r->translations, $locale);
            if ($picked === null) {
                return null;
            }
            [$t, $fallback] = $picked;
            $alternates = [];
            foreach ($r->translations as $tr) {
                $alternates[Shared::localeOf($tr)] = Routes::album((string) $tr->slug);
            }
            $items = [];
            foreach ($r->items as $i) {
                $caption = Locales::pick($i->caption, $locale);
                if ($i->type === GalleryItemType::PHOTO) {
                    $image = Assets::toDto($i->mediaAsset, $locale);
                    if ($image !== null) {
                        $items[] = ['id' => (string) $i->id, 'type' => 'PHOTO', 'image' => $image, 'caption' => $caption];
                    }
                } elseif ($i->videoProvider !== null && $i->videoId) {
                    $items[] = ['id' => (string) $i->id, 'type' => 'VIDEO', 'provider' => Shared::enumValue($i->videoProvider), 'videoId' => (string) $i->videoId, 'caption' => $caption];
                }
            }

            return [
                'id' => (string) $r->id,
                'slug' => (string) $t->slug,
                'title' => (string) $t->title,
                'description' => $t->description,
                'coverImage' => Assets::toDto($r->coverImage, $locale),
                'itemCount' => count($items),
                'takenAt' => Shared::iso($r->takenAt),
                'isFallback' => $fallback,
                'items' => $items,
                'alternates' => $alternates,
            ];
        });
    }
}
