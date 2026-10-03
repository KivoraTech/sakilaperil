<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;

/** Admin reads for transparency reports (port of sakila/lib/queries/admin/reports.ts). */
final class Reports
{
    /**
     * @param  array{q: string, status: string, page: int}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $yearQ = preg_match('/^\d{4}$/D', $p['q']) === 1 ? (int) $p['q'] : null;
        $query = Report::query()
            ->when($p['status'] !== '', fn (Builder $b) => $b->where('status', $p['status']))
            ->when($yearQ !== null, fn (Builder $b) => $b->where('year', $yearQ))
            ->when($yearQ === null && $p['q'] !== '', fn (Builder $b) => SharedContent::titleSearch($b, $p['q']));
        $total = (clone $query)->count();
        $rows = $query->with('translations:id,reportId,locale,title')
            ->orderByDesc('year')->orderBy('position')
            ->limit(ContentCommon::PAGE_SIZE)->offset(($p['page'] - 1) * ContentCommon::PAGE_SIZE)
            ->get();
        $canEdit = ContentCommon::can($user, 'content.editAll');

        return [
            'items' => $rows->map(function (Report $r) use ($canEdit): array {
                $status = ContentCommon::val($r->status);
                $type = ContentCommon::val($r->type);

                return [
                    'id' => $r->id,
                    ...ContentCommon::itTitle($r->translations),
                    'slug' => null,
                    'status' => $status,
                    'scheduled' => ContentCommon::isScheduled($status, $r->publishedAt),
                    'locales' => $r->translations->map(fn ($t) => ContentCommon::val($t->locale))->values()->all(),
                    'updatedAt' => ContentCommon::iso($r->updatedAt),
                    'publishedAt' => ContentCommon::iso($r->publishedAt),
                    'meta' => $r->year.' · '.(ContentForms::REPORT_TYPE_LABELS[$type] ?? $type).($r->fileId ? '' : ' · no PDF'),
                    'canEdit' => $canEdit,
                ];
            })->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => ContentCommon::pageCount($total),
        ];
    }

    /** @return array<string, mixed>|null ReportForEdit of the reference */
    public static function forEdit(string $id): ?array
    {
        ContentCommon::requireCapability('content.editAll');
        $r = Report::query()->with('translations')->find($id);
        if (! $r) {
            return null;
        }
        $it = ContentCommon::byLocale($r->translations, 'it');
        $en = ContentCommon::byLocale($r->translations, 'en');
        $media = Media::dtosByIds([$r->fileId, $r->coverImageId, $en?->fileId]);

        return [
            'id' => $r->id,
            'status' => ContentCommon::val($r->status),
            'values' => [
                'type' => ContentCommon::val($r->type),
                'year' => (int) $r->year,
                'fileId' => $r->fileId,
                'coverImageId' => $r->coverImageId,
                'position' => (int) $r->position,
                'publishedAt' => ContentCommon::isoOrEmpty($r->publishedAt),
                'translations' => [
                    'it' => ['title' => (string) ($it->title ?? ''), 'description' => (string) ($it->description ?? '')],
                    'en' => [
                        'enabled' => $en !== null,
                        'title' => (string) ($en->title ?? ''),
                        'description' => (string) ($en->description ?? ''),
                        'fileId' => $en?->fileId,
                    ],
                ],
            ],
            // Object (not list) even when empty: the form reads assets[id].
            'assets' => (object) $media,
        ];
    }
}
