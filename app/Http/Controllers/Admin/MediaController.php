<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Content\ContentActionError;
use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\Content\Input;
use App\Content\Uploads;
use App\Http\Actions\ActionResult;
use App\Models\MediaAsset;
use App\Models\User;
use App\Queries\Admin\Media;
use App\Storage\Storage;
use App\Storage\UploadValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Media library (port of app/admin/(shell)/media, app/api/media/route.ts, lib/actions/media.ts).
 */
final class MediaController
{
    /** GET /admin/media — capability media.upload (route middleware). */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $one = fn (mixed $v): string => is_array($v) ? (string) ($v[0] ?? '') : (string) ($v ?? '');
        $q = mb_substr(trim($one($request->query('q'))), 0, 100);
        $kindParam = $one($request->query('kind'));
        $kind = in_array($kindParam, ['IMAGE', 'DOCUMENT'], true) ? $kindParam : '';
        $page = (int) $one($request->query('page'));
        $page = max(1, min(10000, $page > 0 ? $page : 1));

        return Inertia::render('admin/media/index', [
            'q' => $q,
            'kind' => $kind,
            'list' => Media::list(['q' => $q, 'kind' => $kind, 'page' => $page, 'pageSize' => 36]),
            'canEdit' => ContentCommon::can($user, 'media.edit'),
            'canDelete' => ContentCommon::can($user, 'media.delete'),
        ]);
    }

    /**
     * POST /api/media — upload ONE file (multipart field "file"). One request per file lets the admin
     * UI show per-file progress. Auth: media.upload, same-origin only (+ CSRF via the web stack).
     * Validation: size pre-check → sniffed MIME (never the client's type/extension) → image processing
     * (orient, strip EXIF/GPS, cap size) → random storage key → MediaAsset row.
     */
    public function upload(Request $request): JsonResponse
    {
        $json = fn (int $status, string $message): JsonResponse => response()->json(['ok' => false, 'message' => $message], $status);

        if (! self::sameOrigin($request)) {
            return $json(403, 'Cross-origin upload rejected.');
        }
        $user = $request->user();
        if (! $user instanceof User) {
            return $json(401, 'Please sign in again.');
        }
        if (! ContentCommon::can($user, 'media.upload')) {
            return $json(403, 'You cannot upload files.');
        }

        // Cheap guard before reading the body (multipart overhead allowance: 1 MB).
        $limit = max(UploadValidator::maxBytesFor('IMAGE'), UploadValidator::maxBytesFor('DOCUMENT')) + 1024 * 1024;
        if ((int) $request->header('Content-Length', '0') > $limit) {
            return $json(413, 'File is too large.');
        }
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            // PHP silently drops a body larger than post_max_size: then nothing at all arrives.
            $droppedBody = (int) $request->header('Content-Length', '0') > 0 && $request->request->count() === 0 && $_FILES === [];

            return $droppedBody ? $json(413, 'File is too large.') : $json(400, 'No file received.');
        }
        if (! $file->isValid()) {
            return $json(in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400, $file->getError() === UPLOAD_ERR_INI_SIZE ? 'File is too large.' : 'Invalid upload.');
        }
        if ($file->getSize() > $limit) {
            return $json(413, 'File is too large.');
        }
        $kindField = $request->input('kind', '');
        $kind = in_array($kindField, ['IMAGE', 'DOCUMENT'], true) ? $kindField : '';
        $altField = $request->input('altIt', '');
        $altIt = is_string($altField) ? Input::trim($altField) : '';
        if (mb_strlen($altIt) > 300) {
            $altIt = '';
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $stored = Uploads::store($bytes, $file->getClientOriginalName(), $kind !== '' ? [$kind] : null);
        if (! $stored['ok']) {
            return $json($stored['error'] === 'TOO_LARGE' ? 413 : 415, $stored['message']);
        }
        $d = $stored['data'];

        try {
            $asset = MediaAsset::query()->create([
                'kind' => $d['kind'],
                'storageDriver' => $d['storageDriver'],
                'storageKey' => $d['storageKey'],
                'url' => $d['url'],
                'filename' => $d['filename'],
                'mimeType' => $d['mimeType'],
                'size' => $d['size'],
                'width' => $d['width'],
                'height' => $d['height'],
                'alt' => $altIt !== '' ? ['it' => $altIt] : null,
                'uploadedById' => $user->id,
            ]);
            $asset->load('uploadedBy:id,name');
            Audit::log(AuditAction::MEDIA_UPLOADED, (string) $user->id, 'MediaAsset', (string) $asset->id, [
                'filename' => $asset->filename, 'mimeType' => $asset->mimeType, 'size' => $asset->size,
            ]);

            return response()->json(['ok' => true, 'asset' => Media::toDto($asset)], 201);
        } catch (Throwable $e) {
            // Don't leave orphaned objects behind.
            try {
                Storage::adapter()->delete($d['storageKey']);
            } catch (Throwable) {
            }
            Log::error('[media] upload failed', ['exception' => $e]);

            return $json(500, 'Upload failed. Please try again.');
        }
    }

    private static function sameOrigin(Request $request): bool
    {
        $origin = $request->header('Origin');
        if (! is_string($origin) || $origin === '') {
            return false;
        }
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);
        if (! is_string($host)) {
            return false;
        }
        $originHost = $host.($port ? ':'.$port : '');

        return strcasecmp($originHost, $request->getHttpHost()) === 0;
    }

    // ── Actions (/admin/actions/media/*) ─────────────────────────────

    /** searchMediaAction({ q, kind, page, pageSize }) — paged search for the MediaPicker dialog. */
    public function search(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $in = Input::from(ContentCommon::bodyAll($request));
            $q = $in->has('q') ? $in->str('q', 100) : '';
            $kind = $in->has('kind') ? $in->enum('kind', ['IMAGE', 'DOCUMENT', '']) : '';
            $page = $in->has('page') ? $in->int('page', 1, 10000) : 1;
            $pageSize = $in->has('pageSize') ? $in->int('pageSize', 1, 60) : 24;
            $in->validate();

            return ActionResult::ok(Media::list(['q' => $q, 'kind' => $kind, 'page' => $page, 'pageSize' => $pageSize]));
        });
    }

    /** getMediaAssetAction(id) */
    public function get(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $asset = Media::get(ContentForms::id(ContentCommon::body($request, 'id')));

            return $asset ? ActionResult::ok($asset) : ActionResult::fail('NOT_FOUND', 'This file no longer exists.');
        });
    }

    /** updateMediaAction(id, { altIt, altEn, captionIt, captionEn, credit, focalX, focalY }) */
    public function update(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('media.edit');
            $id = ContentForms::id(ContentCommon::body($request, 'id'));
            $v = ContentForms::mediaMeta(ContentCommon::body($request, 'input'));
            $asset = MediaAsset::query()->find($id);
            if (! $asset) {
                ContentCommon::notFound('This file no longer exists.');
            }
            $loc = fn (string $it, string $en): ?array => $it !== '' || $en !== '' ? ['it' => $it] + ($en !== '' ? ['en' => $en] : []) : null;
            $asset->update([
                'alt' => $loc($v['altIt'], $v['altEn']) ?? ['it' => ''],
                'caption' => $loc($v['captionIt'], $v['captionEn']) ?? ['it' => ''],
                'credit' => $v['credit'] !== '' ? $v['credit'] : null,
                'focalX' => $v['focalX'],
                'focalY' => $v['focalY'],
            ]);
            Audit::log(AuditAction::MEDIA_UPDATED, (string) $user->id, 'MediaAsset', $id, ['fields' => ['alt', 'caption', 'credit', 'focal']]);
            ContentCommon::expireTags(...ContentCommon::ALL_CONTENT_TAGS);
            $dto = Media::get($id);

            return $dto ? ActionResult::ok($dto, 'Saved') : ActionResult::fail('NOT_FOUND', 'This file no longer exists.');
        });
    }

    /** getMediaUsageAction(id) */
    public function usage(Request $request): JsonResponse
    {
        return ContentActionError::run(fn (): JsonResponse => ActionResult::ok(Media::usage(ContentForms::id(ContentCommon::body($request, 'id')))));
    }

    /**
     * deleteMediaAction(id). BLOCKED while any PUBLISHED content references it. Draft-only references
     * are allowed after the UI has shown them (relations are SET NULL; draft JSON references render nothing).
     */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('media.delete');
            $id = ContentForms::id(ContentCommon::body($request, 'id'));
            $asset = MediaAsset::query()->find($id);
            if (! $asset) {
                ContentCommon::notFound('This file no longer exists.');
            }
            $usage = Media::findUsage($id);
            $live = array_values(array_filter($usage, fn (array $u): bool => $u['published']));
            if ($live !== []) {
                ContentCommon::fail('CONFLICT', 'This file is used by published content ('
                    .implode('; ', array_map(fn (array $u): string => "{$u['label']} – {$u['where']}", array_slice($live, 0, 3)))
                    .(count($live) > 3 ? '…' : '').'). Remove it there first.');
            }

            $asset->delete();
            // Remove the stored object after the row is gone; a failure leaves an orphan file, not a broken row.
            try {
                Storage::for((string) $asset->storageDriver)?->delete((string) $asset->storageKey);
            } catch (Throwable $e) {
                Log::error('[media] could not delete stored object', ['key' => $asset->storageKey, 'error' => $e->getMessage()]);
            }
            Audit::log(AuditAction::MEDIA_DELETED, (string) $user->id, 'MediaAsset', $id, [
                'filename' => $asset->filename, 'draftReferences' => count($usage),
            ]);
            ContentCommon::expireTags(...ContentCommon::ALL_CONTENT_TAGS);

            return ActionResult::ok(['id' => $id], 'File deleted');
        });
    }
}
