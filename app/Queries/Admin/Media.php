<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Admin media reads (port of sakila/lib/queries/admin/media.ts). Public functions re-check the
 * caller's capability; internal helpers are documented as such.
 */
final class Media
{
    /**
     * MediaAssetDTO of the reference (resources/js/lib/content/media.ts).
     *
     * @return array<string, mixed>
     */
    public static function toDto(MediaAsset $r): array
    {
        $alt = is_array($r->alt) ? $r->alt : [];
        $caption = is_array($r->caption) ? $r->caption : [];
        $kind = ContentCommon::val($r->kind);

        return [
            'id' => (string) $r->id,
            'kind' => $kind,
            'url' => (string) $r->url,
            'filename' => (string) $r->filename,
            'mimeType' => (string) $r->mimeType,
            'size' => (int) $r->size,
            'width' => $r->width !== null ? (int) $r->width : null,
            'height' => $r->height !== null ? (int) $r->height : null,
            'alt' => ['it' => is_string($alt['it'] ?? null) ? $alt['it'] : '', 'en' => is_string($alt['en'] ?? null) ? $alt['en'] : ''],
            'caption' => ['it' => is_string($caption['it'] ?? null) ? $caption['it'] : '', 'en' => is_string($caption['en'] ?? null) ? $caption['en'] : ''],
            'credit' => (string) ($r->credit ?? ''),
            'focalX' => $r->focalX !== null ? (float) $r->focalX : null,
            'focalY' => $r->focalY !== null ? (float) $r->focalY : null,
            'storageDriver' => (string) $r->storageDriver,
            'uploadedBy' => $r->uploadedBy?->name,
            'createdAt' => ContentCommon::iso($r->createdAt),
        ];
    }

    /**
     * @param  array{q?: string, kind?: string, page?: int, pageSize?: int}  $opts
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int, pageCount: int}
     */
    public static function list(array $opts = []): array
    {
        ContentCommon::requireCapability('media.upload');
        $pageSize = min(max($opts['pageSize'] ?? 24, 1), 60);
        $page = max($opts['page'] ?? 1, 1);
        $q = trim($opts['q'] ?? '');
        $kind = $opts['kind'] ?? '';

        $query = MediaAsset::query()
            ->when($kind !== '', fn (Builder $b) => $b->where('kind', $kind))
            ->when($q !== '', function (Builder $b) use ($q): void {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $b->where(function (Builder $w) use ($like): void {
                    $w->where('filename', 'like', $like)
                        ->orWhere('credit', 'like', $like)
                        // Same as Prisma's JSON path string_contains on MySQL/MariaDB.
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(`alt`, '$.it')) LIKE ?", [$like])
                        ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(`alt`, '$.en')) LIKE ?", [$like]);
                });
            });
        $total = (clone $query)->count();
        $rows = $query->with('uploadedBy:id,name')
            ->orderByDesc('createdAt')->orderByDesc('id')
            ->limit($pageSize)->offset(($page - 1) * $pageSize)
            ->get();

        return [
            'items' => $rows->map(fn (MediaAsset $r): array => self::toDto($r))->values()->all(),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'pageCount' => max(1, (int) ceil($total / $pageSize)),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function get(string $id): ?array
    {
        ContentCommon::requireCapability('media.upload');
        $r = MediaAsset::query()->with('uploadedBy:id,name')->find($id);

        return $r ? self::toDto($r) : null;
    }

    /**
     * Internal: DTOs for a set of ids (editor previews). Callers must have checked a capability.
     *
     * @param  iterable<string|null>  $ids
     * @return array<string, array<string, mixed>>
     */
    public static function dtosByIds(iterable $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            if (is_string($id) && $id !== '') {
                $unique[$id] = true;
            }
        }
        if ($unique === []) {
            return [];
        }
        $out = [];
        foreach (MediaAsset::query()->with('uploadedBy:id,name')->whereIn('id', array_keys($unique))->get() as $r) {
            $out[(string) $r->id] = self::toDto($r);
        }

        return $out;
    }

    /** @return list<array{kind: string, label: string, href: ?string, published: bool, where: string}> */
    public static function usage(string $id): array
    {
        ContentCommon::requireCapability('media.upload');

        return self::findUsage($id);
    }

    /**
     * Where is this asset used? Every relation (covers, gallery items, report files, OG images) AND
     * JSON content (page drafts, PUBLISHED page snapshots, rich-text bodies, site settings).
     * `published` = deleting it would break live content. Internal: callers check a capability.
     *
     * @return list<array{kind: string, label: string, href: ?string, published: bool, where: string}>
     */
    public static function findUsage(string $id): array
    {
        $out = [];
        $push = function (string $kind, string $label, ?string $href, bool $published, string $where) use (&$out): void {
            $out[] = ['kind' => $kind, 'label' => $label, 'href' => $href, 'published' => $published, 'where' => $where];
        };
        $titles = self::titlesFor(...);

        foreach (DB::table('projects')->where('coverImageId', $id)->get(['id', 'status']) as $p) {
            $push('project', $titles('project_translations', 'projectId', $p->id), "/admin/projects/{$p->id}", $p->status === 'PUBLISHED', 'Cover image');
        }
        foreach (DB::table('news_posts')->where('coverImageId', $id)->get(['id', 'status']) as $n) {
            $push('news', $titles('news_post_translations', 'postId', $n->id), "/admin/news/{$n->id}", $n->status === 'PUBLISHED', 'Cover image');
        }
        foreach (DB::table('events')->where('coverImageId', $id)->get(['id', 'status']) as $e) {
            $push('event', $titles('event_translations', 'eventId', $e->id), "/admin/events/{$e->id}", $e->status === 'PUBLISHED', 'Cover image');
        }
        foreach (DB::table('gallery_albums')->where('coverImageId', $id)->get(['id', 'status']) as $a) {
            $push('album', $titles('gallery_album_translations', 'albumId', $a->id), "/admin/gallery/{$a->id}", $a->status === 'PUBLISHED', 'Album cover');
        }
        $items = DB::table('gallery_items as i')->join('gallery_albums as a', 'a.id', '=', 'i.albumId')
            ->where('i.mediaAssetId', $id)->get(['a.id', 'a.status']);
        foreach ($items as $a) {
            $push('album', $titles('gallery_album_translations', 'albumId', $a->id), "/admin/gallery/{$a->id}", $a->status === 'PUBLISHED', 'Album photo');
        }
        $reports = DB::table('reports')->where(fn ($w) => $w->where('fileId', $id)->orWhere('coverImageId', $id))->get(['id', 'status']);
        foreach ($reports as $r) {
            $push('report', $titles('report_translations', 'reportId', $r->id), "/admin/reports/{$r->id}", $r->status === 'PUBLISHED', 'Report file / cover');
        }
        $reportTrs = DB::table('report_translations as t')->join('reports as r', 'r.id', '=', 't.reportId')
            ->where('t.fileId', $id)->get(['t.locale', 'r.id', 'r.status']);
        foreach ($reportTrs as $t) {
            $push('report', $titles('report_translations', 'reportId', $t->id), "/admin/reports/{$t->id}", $t->status === 'PUBLISHED', 'PDF ('.strtoupper($t->locale).')');
        }
        foreach (DB::table('campaigns')->where('coverImageId', $id)->get(['id', 'status']) as $c) {
            $push('campaign', $titles('campaign_translations', 'campaignId', $c->id), null, $c->status === 'PUBLISHED', 'Campaign cover');
        }
        foreach (DB::table('page_translations')->where('ogImageId', $id)->get(['pageId', 'title', 'locale', 'publishedRevisionId']) as $p) {
            $push('page', "{$p->title} (".strtoupper($p->locale).')', "/admin/pages/{$p->pageId}", $p->publishedRevisionId !== null, 'Social sharing image');
        }

        // JSON references. Ids are cuids ([a-z0-9]); bound as a parameter.
        $like = '%'.addcslashes($id, '%_\\').'%';
        $liveSnapshots = DB::select(
            'SELECT pt.`pageId`, pt.title, pt.locale FROM page_translations pt
             JOIN page_revisions r ON r.id = pt.`publishedRevisionId`
             WHERE CAST(r.snapshot AS CHAR) LIKE ?', [$like]);
        $draftSections = DB::select(
            'SELECT DISTINCT s.`pageId`, pt.title, s.locale FROM page_sections s
             JOIN page_translations pt ON pt.`pageId` = s.`pageId` AND pt.locale = s.locale
             WHERE CAST(s.content AS CHAR) LIKE ?', [$like]);
        $bodies = DB::select(
            "SELECT 'news' AS kind, p.id AS `parentId`, t.title, p.status, t.locale
               FROM news_post_translations t JOIN news_posts p ON p.id = t.`postId` WHERE CAST(t.body AS CHAR) LIKE ?
             UNION ALL
             SELECT 'project', p.id, t.title, p.status, t.locale
               FROM project_translations t JOIN projects p ON p.id = t.`projectId` WHERE CAST(t.body AS CHAR) LIKE ?
             UNION ALL
             SELECT 'event', p.id, t.title, p.status, t.locale
               FROM event_translations t JOIN events p ON p.id = t.`eventId` WHERE CAST(t.body AS CHAR) LIKE ?
             UNION ALL
             SELECT 'campaign', p.id, t.title, p.status, t.locale
               FROM campaign_translations t JOIN campaigns p ON p.id = t.`campaignId` WHERE CAST(t.body AS CHAR) LIKE ?",
            [$like, $like, $like, $like]);
        // Every JSON column of the settings row (keep in sync with the SiteSettings model).
        $settings = DB::selectOne(
            "SELECT count(*) AS n FROM site_settings s
             WHERE CONCAT_WS(' ', CAST(s.organization AS CHAR), CAST(s.contact AS CHAR), CAST(s.`socialLinks` AS CHAR),
               CAST(s.`bankDetails` AS CHAR), CAST(s.`nepalPayments` AS CHAR), CAST(s.donation AS CHAR),
               CAST(s.`impactStats` AS CHAR), CAST(s.seo AS CHAR), CAST(s.appearance AS CHAR), CAST(s.analytics AS CHAR),
               CAST(s.`formMessages` AS CHAR), CAST(s.features AS CHAR)) LIKE ?", [$like]);

        foreach ($liveSnapshots as $p) {
            $push('page', "{$p->title} (".strtoupper($p->locale).')', "/admin/pages/{$p->pageId}", true, 'Published page content');
        }
        foreach ($draftSections as $p) {
            $push('page', "{$p->title} (".strtoupper($p->locale).')', "/admin/pages/{$p->pageId}", false, 'Page draft');
        }
        $hrefFor = ['news' => '/admin/news/', 'project' => '/admin/projects/', 'event' => '/admin/events/'];
        foreach ($bodies as $b) {
            $push((string) $b->kind, "{$b->title} (".strtoupper($b->locale).')', isset($hrefFor[$b->kind]) ? $hrefFor[$b->kind].$b->parentId : null, $b->status === 'PUBLISHED', 'Body text');
        }
        if ((int) ($settings->n ?? 0) > 0) {
            $push('settings', 'Site settings', '/admin/settings', true, 'Settings (e.g. QR code / social image)');
        }

        return $out;
    }

    /** Italian title (fallback any) of a parent row. */
    private static function titlesFor(string $table, string $fk, string $parentId): string
    {
        $rows = DB::table($table)->where($fk, $parentId)->get(['locale', 'title']);
        foreach ($rows as $r) {
            if ($r->locale === 'it') {
                return (string) $r->title;
            }
        }

        return isset($rows[0]) ? (string) $rows[0]->title : '(untitled)';
    }
}
