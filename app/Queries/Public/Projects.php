<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\Project;
use App\Models\ProjectTranslation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Port of lib/queries/public/projects.ts.
 * ProjectSummary: {id, slug, title, sector, metaLine, summary, stage, district, goalAmount, raisedAmount,
 * currency, coverImage, isFeatured, isFallback, publishedAt}; ProjectDetail adds body, seoTitle,
 * seoDescription, startDate, endDate, alternates.
 */
final class Projects
{
    /**
     * @param  array{featuredOnly?: bool, ids?: list<string>, limit?: int}  $opts
     * @return list<array<string, mixed>>
     */
    public static function list(string $locale, array $opts = []): array
    {
        $key = 'projects:'.$locale.':'.md5((string) json_encode($opts));

        return TagCache::remember([CacheTags::PROJECTS], $key, function () use ($locale, $opts): array {
            $ids = $opts['ids'] ?? [];
            $q = Shared::published(Project::query())
                ->when(! empty($opts['featuredOnly']), fn (Builder $b) => $b->where('isFeatured', true))
                ->when($ids !== [], fn (Builder $b) => $b->whereIn('id', $ids))
                ->with(['translations' => fn ($t) => $t->whereIn('locale', Shared::translationLocales($locale)), 'coverImage'])
                ->orderBy('position')
                ->orderByDesc('publishedAt');
            if (isset($opts['limit'])) {
                $q->limit((int) $opts['limit']);
            }
            $list = [];
            foreach ($q->get() as $row) {
                $s = self::summary($row, $locale);
                if ($s !== null) {
                    $list[] = $s;
                }
            }
            if ($ids !== []) {
                $byId = array_column($list, null, 'id');

                return array_values(array_filter(array_map(fn (string $id) => $byId[$id] ?? null, $ids)));
            }

            return $list;
        });
    }

    /**
     * Project by localized slug; also matches the default-locale slug (fallback content).
     *
     * @return array<string, mixed>|null
     */
    public static function get(string $locale, string $slug): ?array
    {
        return TagCache::remember([CacheTags::PROJECTS], "project:{$locale}:{$slug}", function () use ($locale, $slug): ?array {
            $locales = Shared::translationLocales($locale);
            $row = Shared::published(Project::query())
                ->whereHas('translations', fn (Builder $t) => $t->where('slug', $slug)->whereIn('locale', $locales))
                ->with(['translations', 'coverImage'])
                ->first();
            if ($row === null) {
                return null;
            }
            $summary = self::summary($row, $locale, $row->translations->filter(fn ($t) => in_array(Shared::localeOf($t), $locales, true)));
            if ($summary === null) {
                return null;
            }
            [$t] = Shared::pick($row->translations, $locale) ?? [null];
            $alternates = [];
            foreach ($row->translations as $tr) {
                $alternates[Shared::localeOf($tr)] = Routes::project((string) $tr->slug);
            }

            return [
                ...$summary,
                'body' => is_array($t?->body) ? $t->body : null,
                'seoTitle' => $t?->seoTitle,
                'seoDescription' => $t?->seoDescription,
                'startDate' => Shared::iso($row->startDate),
                'endDate' => Shared::iso($row->endDate),
                'alternates' => $alternates,
            ];
        });
    }

    /**
     * @param  iterable<ProjectTranslation>|null  $translations
     * @return array<string, mixed>|null
     */
    private static function summary(Project $row, string $locale, ?iterable $translations = null): ?array
    {
        $picked = Shared::pick($translations ?? $row->translations, $locale);
        if ($picked === null) {
            return null;
        }
        [$t, $fallback] = $picked;

        return [
            'id' => (string) $row->id,
            'slug' => (string) $t->slug,
            'title' => (string) $t->title,
            'sector' => $t->sector,
            'metaLine' => $t->metaLine,
            'summary' => $t->summary,
            'stage' => Shared::enumValue($row->stage),
            'district' => $row->district,
            'goalAmount' => $row->goalAmount,
            'raisedAmount' => $row->raisedAmount,
            'currency' => (string) $row->currency,
            'coverImage' => Assets::toDto($row->coverImage, $locale),
            'isFeatured' => (bool) $row->isFeatured,
            'isFallback' => $fallback,
            'publishedAt' => Shared::iso($row->publishedAt),
        ];
    }
}
