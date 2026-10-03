<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Cache\CacheTags;
use App\Content\ContentActionError;
use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\Content\ContentSave;
use App\Http\Actions\ActionResult;
use App\Models\GalleryAlbum;
use App\Models\GalleryAlbumTranslation;
use App\Models\GalleryItem;
use App\Models\Project;
use App\Models\User;
use App\Queries\Admin\Gallery;
use App\Queries\Admin\Projects;
use App\RichText\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Gallery albums (port of app/admin/(shell)/gallery/** and lib/actions/gallery.ts): photos from the
 * media library + YouTube/Vimeo embeds, ordered. Albums have no owner field, so managing them
 * requires content.editAll (editors+).
 */
final class GalleryController
{
    private const EMPTY_ALBUM = 'Add at least one photo or video before publishing this album.';

    /** @return list<string> */
    private static function tagsFor(string $id): array
    {
        return [CacheTags::ALBUMS, CacheTags::album($id), CacheTags::PAGES];
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $params = ContentCommon::listParams($request->query());

        return Inertia::render('admin/gallery/index', [
            'params' => $params,
            'list' => Gallery::list($params),
            'canManage' => ContentCommon::can($user, 'content.editAll'),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/gallery/edit', [
            'id' => null,
            'status' => null,
            'initialValues' => null,
            'coverAsset' => null,
            'perms' => ['canEdit' => true, 'canPublish' => ContentCommon::can($user, 'content.publish'), 'canDelete' => false],
            'publicSlug' => null,
            'projects' => Projects::options(),
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $album = Gallery::forEdit($id);
        if ($album === null) {
            return AdminNotFound::response($request, 'album', '/admin/gallery', 'Back to gallery albums');
        }

        return Inertia::render('admin/gallery/edit', [
            'id' => $album['id'],
            'status' => $album['status'],
            'initialValues' => $album['values'],
            'coverAsset' => $album['coverAsset'],
            'perms' => [
                'canEdit' => true,
                'canPublish' => ContentCommon::can($user, 'content.publish'),
                'canDelete' => ContentCommon::can($user, 'content.delete'),
            ],
            'publicSlug' => $album['slugs']['it'],
            'projects' => Projects::options(),
        ]);
    }

    /** saveAlbumAction(id, input, intent) */
    public function save(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.editAll');
            $intent = ContentForms::intent(ContentCommon::body($request, 'intent'));
            $rawId = ContentCommon::body($request, 'id');
            $albumId = $rawId ? ContentForms::id($rawId) : null;
            $v = ContentForms::album(ContentCommon::body($request, 'input'));
            if ($intent === 'publish' && ! ContentCommon::can($user, 'content.publish')) {
                ContentCommon::fail('FORBIDDEN', 'Only editors can publish.');
            }

            $existing = $albumId ? GalleryAlbum::query()->find($albumId, ['id', 'status', 'publishedAt']) : null;
            if ($albumId && ! $existing) {
                ContentCommon::notFound('This album no longer exists.');
            }

            $tr = $v['translations'];
            $locales = ContentSave::locales($tr);
            $conflicts = ContentCommon::slugConflicts('album', $locales, $albumId);
            if ($conflicts !== []) {
                ContentCommon::slugConflict($conflicts);
            }

            $photoIds = [];
            $refs = [['id' => $v['coverImageId'], 'kind' => 'IMAGE', 'field' => 'coverImageId']];
            foreach ($v['items'] as $n => $i) {
                if ($i['type'] === 'PHOTO') {
                    $photoIds[] = $i['mediaAssetId'];
                }
                $refs[] = ['id' => $i['type'] === 'PHOTO' ? $i['mediaAssetId'] : null, 'kind' => 'IMAGE', 'field' => "items.{$n}.mediaAssetId"];
            }
            ContentCommon::throwIfErrors(ContentCommon::assertMediaKinds($refs), 'Some photos no longer exist.');
            ContentCommon::throwIfErrors(ContentCommon::assertReferences([
                ['id' => $v['projectId'], 'model' => Project::class, 'field' => 'projectId', 'label' => 'project'],
            ]));

            $existingStatus = $existing ? ContentCommon::val($existing->status) : null;
            $status = $intent === 'publish' ? 'PUBLISHED' : ($existingStatus ?? 'DRAFT');
            if ($status === 'PUBLISHED') {
                if ($v['items'] === []) {
                    ContentCommon::fail('VALIDATION', self::EMPTY_ALBUM, ['items' => ['A published album needs at least one photo or video']]);
                }
                $missing = ContentCommon::imagesMissingAlt([$v['coverImageId'], ...$photoIds]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }

            $data = [
                'status' => $status,
                'coverImageId' => $v['coverImageId'],
                'projectId' => $v['projectId'],
                'takenAt' => ContentCommon::dateOrNull($v['takenAt']),
                'position' => $v['position'],
                'publishedAt' => ContentSave::publishedAt($v['publishedAt'], $status, $existing?->publishedAt),
            ];

            $items = [];
            foreach ($v['items'] as $position => $i) {
                $caption = $i['captionIt'] !== '' || $i['captionEn'] !== ''
                    ? ['it' => $i['captionIt']] + ($i['captionEn'] !== '' ? ['en' => $i['captionEn']] : [])
                    : null;
                if ($i['type'] === 'PHOTO') {
                    $items[] = ['type' => 'PHOTO', 'mediaAssetId' => $i['mediaAssetId'], 'caption' => $caption, 'position' => $position];

                    continue;
                }
                if (! Video::isValidId($i['videoProvider'], $i['videoId'])) {
                    throw new RuntimeException('invalid video');
                }
                $items[] = [
                    'type' => 'VIDEO',
                    'videoProvider' => $i['videoProvider'] === 'youtube' ? 'YOUTUBE' : 'VIMEO',
                    'videoId' => $i['videoId'],
                    'caption' => $caption,
                    'position' => $position,
                ];
            }

            $savedId = DB::transaction(function () use ($albumId, $data, $locales, $tr, $items): string {
                $album = $albumId ? tap(GalleryAlbum::query()->findOrFail($albumId))->update($data) : GalleryAlbum::query()->create($data);
                $rows = array_map(fn (array $l): array => ['locale' => $l['locale'], 'fields' => [
                    'title' => $l['title'], 'slug' => $l['slug'], 'description' => ContentCommon::blank($l['description']),
                ]], $locales);
                ContentSave::upsertTranslations(GalleryAlbumTranslation::class, 'albumId', $album->id, $rows, (bool) $tr['en']['enabled']);
                // Items are replaced wholesale: simple, ordered and small (≤300 rows).
                GalleryItem::query()->where('albumId', $album->id)->delete();
                foreach ($items as $item) {
                    GalleryItem::query()->create([...$item, 'albumId' => $album->id]);
                }

                return (string) $album->id;
            });

            Audit::log($albumId ? AuditAction::CONTENT_UPDATED : AuditAction::CONTENT_CREATED, (string) $user->id, 'GalleryAlbum', $savedId, [
                'title' => $tr['it']['title'], 'status' => $status, 'items' => count($items),
            ]);
            if ($status === 'PUBLISHED' && $existingStatus !== 'PUBLISHED') {
                Audit::log(AuditAction::CONTENT_PUBLISHED, (string) $user->id, 'GalleryAlbum', $savedId);
            }
            ContentCommon::expireTags(...self::tagsFor($savedId));

            return ActionResult::ok(['id' => $savedId], ContentCommon::savedMessage($status, $data['publishedAt']));
        }, slugConflictOnUnique: true);
    }

    /** setAlbumStatusAction(id, status) */
    public function setStatus(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.publish');
            $albumId = ContentForms::id(ContentCommon::body($request, 'id'));
            $status = ContentForms::status(ContentCommon::body($request, 'status'));
            $album = GalleryAlbum::query()->with('items:id,albumId,mediaAssetId')->find($albumId);
            if (! $album) {
                ContentCommon::notFound('This album no longer exists.');
            }
            if ($status === 'PUBLISHED') {
                if ($album->items->isEmpty()) {
                    ContentCommon::fail('VALIDATION', self::EMPTY_ALBUM);
                }
                $missing = ContentCommon::imagesMissingAlt([$album->coverImageId, ...$album->items->pluck('mediaAssetId')->all()]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }
            $from = ContentCommon::val($album->status);
            $album->update(['status' => $status] + ($status === 'PUBLISHED' && ! $album->publishedAt ? ['publishedAt' => now()] : []));
            Audit::log(ContentCommon::statusAuditAction($status), (string) $user->id, 'GalleryAlbum', $albumId, ['from' => $from, 'to' => $status]);
            ContentCommon::expireTags(...self::tagsFor($albumId));

            return ActionResult::ok(['id' => $albumId], ContentCommon::statusMessage($status, $album->publishedAt));
        });
    }

    /** deleteAlbumAction(id) — photos stay in the media library; only the album and its item rows go. */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.delete');
            $albumId = ContentForms::id(ContentCommon::body($request, 'id'));
            $album = GalleryAlbum::query()->with(['translations' => fn ($q) => $q->where('locale', 'it')])->find($albumId);
            if (! $album) {
                ContentCommon::notFound('This album no longer exists.');
            }
            $album->delete();
            Audit::log(AuditAction::CONTENT_DELETED, (string) $user->id, 'GalleryAlbum', $albumId, [
                'title' => $album->translations->first()?->title, 'status' => ContentCommon::val($album->status),
            ]);
            ContentCommon::expireTags(...self::tagsFor($albumId));

            return ActionResult::ok(['id' => $albumId], 'Album deleted');
        });
    }
}
