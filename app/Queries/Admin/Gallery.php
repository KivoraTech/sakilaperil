<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\RichText\Video;
use Illuminate\Database\Eloquent\Builder;

/** Admin reads for gallery albums (port of sakila/lib/queries/admin/gallery.ts). */
final class Gallery
{
    /**
     * @param  array{q: string, status: string, page: int}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $query = GalleryAlbum::query()
            ->when($p['status'] !== '', fn (Builder $b) => $b->where('status', $p['status']))
            ->when($p['q'] !== '', fn (Builder $b) => SharedContent::titleSearch($b, $p['q']));
        $total = (clone $query)->count();
        $rows = $query->with('translations:id,albumId,locale,title,slug')->withCount('items')
            ->orderBy('position')->orderByDesc('updatedAt')
            ->limit(ContentCommon::PAGE_SIZE)->offset(($p['page'] - 1) * ContentCommon::PAGE_SIZE)
            ->get();
        $canEdit = ContentCommon::can($user, 'content.editAll');

        return [
            'items' => $rows->map(function (GalleryAlbum $r) use ($canEdit): array {
                $status = ContentCommon::val($r->status);

                return [
                    'id' => $r->id,
                    ...ContentCommon::itTitle($r->translations),
                    'status' => $status,
                    'scheduled' => ContentCommon::isScheduled($status, $r->publishedAt),
                    'locales' => $r->translations->map(fn ($t) => ContentCommon::val($t->locale))->values()->all(),
                    'updatedAt' => ContentCommon::iso($r->updatedAt),
                    'publishedAt' => ContentCommon::iso($r->publishedAt),
                    'meta' => ((int) $r->getAttribute('items_count')).' item(s)',
                    'canEdit' => $canEdit,
                ];
            })->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => ContentCommon::pageCount($total),
        ];
    }

    /** @return array<string, mixed>|null AlbumForEdit of the reference */
    public static function forEdit(string $id): ?array
    {
        ContentCommon::requireCapability('content.editAll');
        $r = GalleryAlbum::query()->with(['translations', 'items' => fn ($q) => $q->orderBy('position')])->find($id);
        if (! $r) {
            return null;
        }
        $it = ContentCommon::byLocale($r->translations, 'it');
        $en = ContentCommon::byLocale($r->translations, 'en');
        $media = Media::dtosByIds([$r->coverImageId, ...$r->items->pluck('mediaAssetId')->all()]);
        $tr = fn ($t): array => [
            'title' => (string) ($t->title ?? ''),
            'slug' => (string) ($t->slug ?? ''),
            'description' => (string) ($t->description ?? ''),
        ];

        return [
            'id' => $r->id,
            'status' => ContentCommon::val($r->status),
            'values' => [
                'coverImageId' => $r->coverImageId,
                'projectId' => $r->projectId,
                'takenAt' => ContentCommon::isoOrEmpty($r->takenAt),
                'position' => (int) $r->position,
                'publishedAt' => ContentCommon::isoOrEmpty($r->publishedAt),
                'items' => $r->items->map(function (GalleryItem $i) use ($media): array {
                    $caption = is_array($i->caption) ? $i->caption : [];
                    $vp = $i->videoProvider !== null ? ContentCommon::val($i->videoProvider) : null;
                    $provider = $vp === 'YOUTUBE' ? 'youtube' : ($vp === 'VIMEO' ? 'vimeo' : null);
                    $type = ContentCommon::val($i->type);

                    return [
                        'key' => $i->id,
                        'type' => $type,
                        'mediaAssetId' => $i->mediaAssetId,
                        'videoProvider' => $provider,
                        'videoId' => $i->videoId,
                        'captionIt' => is_string($caption['it'] ?? null) ? $caption['it'] : '',
                        'captionEn' => is_string($caption['en'] ?? null) ? $caption['en'] : '',
                        'previewUrl' => $type === 'PHOTO'
                            ? ($media[$i->mediaAssetId ?? '']['url'] ?? null)
                            : ($provider !== null && $i->videoId ? Video::thumbnailUrl($provider, (string) $i->videoId) : null),
                    ];
                })->values()->all(),
                'translations' => ['it' => $tr($it), 'en' => [...$tr($en), 'enabled' => $en !== null]],
            ],
            'coverAsset' => $r->coverImageId ? ($media[$r->coverImageId] ?? null) : null,
            'slugs' => ['it' => $it?->slug, 'en' => $en?->slug],
        ];
    }
}
