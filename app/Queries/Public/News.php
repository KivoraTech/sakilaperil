<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\Role;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Port of lib/queries/public/news.ts.
 * NewsSummary: {id, slug, title, excerpt, publishedAt, coverImage, category{id,key,name}|null, isFallback};
 * NewsDetail adds body, seoTitle, seoDescription, tags[{id,name,slug}], projectId, authorName, alternates.
 */
final class News
{
    /**
     * @param  array{limit?: int, page?: int, categoryId?: string|null, projectId?: string|null}  $opts
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(string $locale, array $opts = []): array
    {
        $key = 'news:'.$locale.':'.md5((string) json_encode($opts));

        return TagCache::remember([CacheTags::NEWS], $key, function () use ($locale, $opts): array {
            $limit = min($opts['limit'] ?? 12, 50);
            $page = max($opts['page'] ?? 1, 1);
            $locales = Shared::translationLocales($locale);
            $base = fn () => Shared::published(NewsPost::query())
                ->when(! empty($opts['categoryId']), fn (Builder $b) => $b->where('categoryId', $opts['categoryId']))
                ->when(! empty($opts['projectId']), fn (Builder $b) => $b->where('projectId', $opts['projectId']));
            $rows = $base()
                ->with([
                    'translations' => fn ($t) => $t->whereIn('locale', $locales),
                    'coverImage',
                    'category.translations' => fn ($t) => $t->whereIn('locale', $locales),
                ])
                ->orderByDesc('publishedAt')
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->get();
            $total = $base()->count();
            $items = [];
            foreach ($rows as $r) {
                $picked = Shared::pick($r->translations, $locale);
                if ($picked === null) {
                    continue;
                }
                [$t, $fallback] = $picked;
                $items[] = [
                    'id' => (string) $r->id,
                    'slug' => (string) $t->slug,
                    'title' => (string) $t->title,
                    'excerpt' => $t->excerpt,
                    'publishedAt' => Shared::iso($r->publishedAt),
                    'coverImage' => Assets::toDto($r->coverImage, $locale),
                    'category' => self::category($r, $locale),
                    'isFallback' => $fallback,
                ];
            }

            return ['items' => $items, 'total' => $total, 'page' => $page, 'pageCount' => max(1, (int) ceil($total / $limit))];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $locale, string $slug): ?array
    {
        return TagCache::remember([CacheTags::NEWS], "news-post:{$locale}:{$slug}", function () use ($locale, $slug): ?array {
            $r = Shared::published(NewsPost::query())
                ->whereHas('translations', fn (Builder $t) => $t->where('slug', $slug)->whereIn('locale', Shared::translationLocales($locale)))
                ->with(['translations', 'coverImage', 'category.translations', 'tags.tag.translations', 'author:id,name,role'])
                ->first();
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
                $alternates[Shared::localeOf($tr)] = Routes::news((string) $tr->slug);
            }
            $tags = [];
            foreach ($r->tags as $pt) {
                $tt = $pt->tag !== null ? Shared::pick($pt->tag->translations, $locale) : null;
                if ($tt !== null) {
                    $tags[] = ['id' => (string) $pt->tag->id, 'name' => (string) $tt[0]->name, 'slug' => (string) $tt[0]->slug];
                }
            }

            return [
                'id' => (string) $r->id,
                'slug' => (string) $t->slug,
                'title' => (string) $t->title,
                'excerpt' => $t->excerpt,
                'publishedAt' => Shared::iso($r->publishedAt),
                'coverImage' => Assets::toDto($r->coverImage, $locale),
                'category' => self::category($r, $locale),
                'isFallback' => $fallback,
                'body' => is_array($t->body) ? $t->body : null,
                'seoTitle' => $t->seoTitle,
                'seoDescription' => $t->seoDescription,
                'tags' => $tags,
                'projectId' => $r->projectId,
                'authorName' => self::publicAuthorName($r->author),
                'alternates' => $alternates,
            ];
        });
    }

    /**
     * The byline shown on the public site. Stories written by a person with a writing role
     * (author, editor) carry their name; stories filed under an administrator account are
     * published in the organisation's name (no account name is shown).
     */
    private static function publicAuthorName(?User $author): ?string
    {
        if ($author === null || ! in_array($author->role, [Role::AUTHOR, Role::EDITOR], true)) {
            return null;
        }
        $name = trim((string) $author->name);

        return $name !== '' ? $name : null;
    }

    /**
     * Categories that have at least one published post (News filter chips).
     *
     * @return list<array{id: string, key: string, name: string, slug: string}>
     */
    public static function categories(string $locale): array
    {
        return TagCache::remember([CacheTags::NEWS], "news-categories:{$locale}", function () use ($locale): array {
            $rows = NewsCategory::query()
                ->whereHas('posts', fn (Builder $p) => Shared::published($p))
                ->with(['translations' => fn ($t) => $t->whereIn('locale', Shared::translationLocales($locale))])
                ->get();
            $out = [];
            foreach ($rows as $r) {
                $picked = Shared::pick($r->translations, $locale);
                if ($picked !== null) {
                    $out[] = ['id' => (string) $r->id, 'key' => (string) $r->key, 'name' => (string) $picked[0]->name, 'slug' => (string) $picked[0]->slug];
                }
            }
            usort($out, fn (array $a, array $b): int => self::localeCompare($a['name'], $b['name']));

            return $out;
        });
    }

    /**
     * @return array{id: string, key: string, name: string}|null
     */
    private static function category(NewsPost $r, string $locale): ?array
    {
        if ($r->category === null) {
            return null;
        }
        $ct = Shared::pick($r->category->translations, $locale);

        return $ct !== null ? ['id' => (string) $r->category->id, 'key' => (string) $r->category->key, 'name' => (string) $ct[0]->name] : null;
    }

    /** String#localeCompare for it/en names without the intl extension (accents folded, case-insensitive first). */
    private static function localeCompare(string $a, string $b): int
    {
        $fold = static function (string $s): string {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

            return strtolower($t !== false ? $t : $s);
        };

        return strcmp($fold($a), $fold($b)) ?: strcmp($a, $b);
    }
}
