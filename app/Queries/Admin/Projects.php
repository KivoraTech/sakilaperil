<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;

/** Admin reads for projects (port of sakila/lib/queries/admin/projects.ts). */
final class Projects
{
    private const STAGE_LABEL = ['PLANNED' => 'Planned', 'ACTIVE' => 'Active', 'COMPLETED' => 'Completed'];

    /**
     * @param  array{q: string, status: string, page: int}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $query = Project::query()
            ->when($p['status'] !== '', fn (Builder $b) => $b->where('status', $p['status']))
            ->when($p['q'] !== '', fn (Builder $b) => SharedContent::titleSearch($b, $p['q']));
        $total = (clone $query)->count();
        $rows = $query->with('translations:id,projectId,locale,title,slug')
            ->orderBy('position')->orderByDesc('updatedAt')
            ->limit(ContentCommon::PAGE_SIZE)->offset(($p['page'] - 1) * ContentCommon::PAGE_SIZE)
            ->get();
        $mayPublish = ContentCommon::can($user, 'content.publish');

        return [
            'items' => $rows->map(function (Project $r) use ($user, $mayPublish): array {
                $status = ContentCommon::val($r->status);

                return [
                    'id' => $r->id,
                    ...ContentCommon::itTitle($r->translations),
                    'status' => $status,
                    'scheduled' => ContentCommon::isScheduled($status, $r->publishedAt),
                    'locales' => $r->translations->map(fn ($t) => ContentCommon::val($t->locale))->values()->all(),
                    'updatedAt' => ContentCommon::iso($r->updatedAt),
                    'publishedAt' => ContentCommon::iso($r->publishedAt),
                    'meta' => implode(' · ', array_values(array_filter([self::STAGE_LABEL[ContentCommon::val($r->stage)] ?? null, $r->district]))),
                    'canEdit' => ContentCommon::canEditContent($user, $r->createdById) && ($status === 'DRAFT' || $mayPublish),
                ];
            })->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => ContentCommon::pageCount($total),
        ];
    }

    /** @return array<string, mixed>|null ProjectForEdit of the reference */
    public static function forEdit(string $id): ?array
    {
        $user = ContentCommon::requireCapability('content.create');
        $r = Project::query()->with(['translations', 'albums.translations' => fn ($q) => $q->where('locale', 'it')])->find($id);
        if (! $r) {
            return null;
        }
        $it = ContentCommon::byLocale($r->translations, 'it');
        $en = ContentCommon::byLocale($r->translations, 'en');
        $tr = fn ($t): array => [
            'title' => (string) ($t->title ?? ''),
            'slug' => (string) ($t->slug ?? ''),
            'sector' => (string) ($t->sector ?? ''),
            'metaLine' => (string) ($t->metaLine ?? ''),
            'summary' => (string) ($t->summary ?? ''),
            'body' => ContentCommon::docOrEmpty($t?->body),
            'seoTitle' => (string) ($t->seoTitle ?? ''),
            'seoDescription' => (string) ($t->seoDescription ?? ''),
        ];
        $media = Media::dtosByIds([$r->coverImageId]);
        $status = ContentCommon::val($r->status);
        $currency = in_array($r->currency, ContentForms::CURRENCIES, true) ? $r->currency : 'EUR';

        return [
            'id' => $r->id,
            'status' => $status,
            'values' => [
                'stage' => ContentCommon::val($r->stage),
                'district' => (string) ($r->district ?? ''),
                'latitude' => $r->latitude !== null ? self::jsNumber($r->latitude) : '',
                'longitude' => $r->longitude !== null ? self::jsNumber($r->longitude) : '',
                'startDate' => ContentCommon::isoOrEmpty($r->startDate),
                'endDate' => ContentCommon::isoOrEmpty($r->endDate),
                'goalAmount' => ContentForms::fromMinorUnits($r->goalAmount),
                'raisedAmount' => ContentForms::fromMinorUnits($r->raisedAmount),
                'currency' => $currency,
                'coverImageId' => $r->coverImageId,
                'isFeatured' => (bool) $r->isFeatured,
                'position' => (int) $r->position,
                'publishedAt' => ContentCommon::isoOrEmpty($r->publishedAt),
                'translations' => ['it' => $tr($it), 'en' => [...$tr($en), 'enabled' => $en !== null]],
            ],
            'coverAsset' => $r->coverImageId ? ($media[$r->coverImageId] ?? null) : null,
            'slugs' => ['it' => $it?->slug, 'en' => $en?->slug],
            'albums' => $r->albums->map(fn ($a): array => [
                'id' => $a->id,
                'title' => (string) ($a->translations->first()->title ?? '(untitled)'),
                'status' => ContentCommon::val($a->status),
            ])->values()->all(),
            'canEdit' => ContentCommon::canEditContent($user, $r->createdById) && ($status === 'DRAFT' || ContentCommon::can($user, 'content.publish')),
        ];
    }

    /**
     * Select options (news/gallery forms).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(bool $check = true): array
    {
        if ($check) {
            ContentCommon::requireCapability('content.create');
        }

        return Project::query()->orderBy('position')->orderByDesc('createdAt')
            ->with(['translations' => fn ($q) => $q->where('locale', 'it')->select(['id', 'projectId', 'locale', 'title'])])
            ->get()
            ->map(fn (Project $p): array => ['value' => $p->id, 'label' => (string) ($p->translations->first()->title ?? '(untitled)')])
            ->values()->all();
    }

    /** @return array<string, mixed>|null ProjectDetail of the public site + status */
    public static function draftForPreview(string $id, string $locale): ?array
    {
        ContentCommon::requireCapability('content.create');
        $r = Project::query()->with(['translations', 'coverImage'])->find($id);
        if (! $r) {
            return null;
        }
        $t = SharedContent::pickTranslation($r->translations, $locale);
        if ($t === null) {
            return null;
        }
        $row = $t['row'];
        $alternates = [];
        foreach ($r->translations as $tr) {
            $alternates[ContentCommon::val($tr->locale)] = Routes::project((string) $tr->slug);
        }

        return [
            'status' => ContentCommon::val($r->status),
            'id' => $r->id,
            'slug' => (string) $row->slug,
            'title' => (string) $row->title,
            'sector' => $row->sector,
            'metaLine' => $row->metaLine,
            'summary' => $row->summary,
            'stage' => ContentCommon::val($r->stage),
            'district' => $r->district,
            'goalAmount' => $r->goalAmount,
            'raisedAmount' => $r->raisedAmount,
            'currency' => $r->currency,
            'coverImage' => Assets::toDto($r->coverImage, $locale),
            'isFeatured' => (bool) $r->isFeatured,
            'isFallback' => $t['isFallback'],
            'publishedAt' => ContentCommon::iso($r->publishedAt),
            'body' => is_array($row->body) ? $row->body : null,
            'seoTitle' => $row->seoTitle,
            'seoDescription' => $row->seoDescription,
            'startDate' => ContentCommon::iso($r->startDate),
            'endDate' => ContentCommon::iso($r->endDate),
            'alternates' => $alternates,
        ];
    }

    /** Number#toString() for a coordinate (no trailing ".0"). */
    private static function jsNumber(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }
}
