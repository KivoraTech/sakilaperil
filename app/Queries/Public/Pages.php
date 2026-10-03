<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\ContentStatus;
use App\Models\PageTranslation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Port of lib/queries/public/pages.ts. Published pages come ONLY from the published revision
 * snapshot (never from page_sections), so drafts can't leak.
 *
 * PublicPage: {id, key, locale, slug, title, seoTitle, seoDescription, ogImageId, noIndex,
 * publishedAt, sections, alternates}
 */
final class Pages
{
    /**
     * Published page by localized slug ("home" for the home page).
     *
     * @return array<string, mixed>|null
     */
    public static function bySlug(string $locale, string $slug): ?array
    {
        return TagCache::remember([CacheTags::PAGES], "page-by-slug:{$locale}:{$slug}", fn () => self::load($locale, slug: $slug));
    }

    /**
     * Published system page by key ("home", "about", "transparency"…).
     *
     * @return array<string, mixed>|null
     */
    public static function byKey(string $locale, string $key): ?array
    {
        return TagCache::remember([CacheTags::PAGES], "page-by-key:{$locale}:{$key}", fn () => self::load($locale, key: $key));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function load(string $locale, ?string $slug = null, ?string $key = null): ?array
    {
        $t = PageTranslation::query()
            ->where('locale', $locale)
            ->when($slug !== null, fn (Builder $q) => $q->where('slug', $slug))
            ->whereNotNull('publishedRevisionId')
            ->whereHas('page', fn (Builder $q) => $q->where('status', ContentStatus::PUBLISHED->value)
                ->when($key !== null, fn (Builder $p) => $p->where('key', $key)))
            ->with(['publishedRevision', 'page.translations' => fn ($q) => $q->whereNotNull('publishedRevisionId')->select(['id', 'pageId', 'locale', 'slug'])])
            ->first();
        $snapshot = $t?->publishedRevision?->snapshot;
        if ($t === null || ! is_array($snapshot) || ! isset($snapshot['title'], $snapshot['sections']) || ! is_array($snapshot['sections'])) {
            if ($t !== null) {
                logger()->error('[pages] invalid snapshot', ['revision' => $t->publishedRevisionId]);
            }

            return null;
        }
        $alternates = [];
        foreach ($t->page->translations as $tr) {
            $alternates[Shared::localeOf($tr)] = $tr->slug === 'home' ? '/' : '/'.$tr->slug;
        }

        return [
            'id' => (string) $t->page->id,
            'key' => $t->page->key,
            'locale' => Shared::localeOf($t),
            'slug' => (string) $t->slug,
            'title' => (string) $snapshot['title'],
            'seoTitle' => $snapshot['seoTitle'] ?? null,
            'seoDescription' => $snapshot['seoDescription'] ?? null,
            'ogImageId' => $snapshot['ogImageId'] ?? null,
            'noIndex' => (bool) ($snapshot['noIndex'] ?? false),
            'publishedAt' => Shared::iso($t->publishedAt),
            'sections' => array_values($snapshot['sections']),
            'alternates' => $alternates,
        ];
    }
}
