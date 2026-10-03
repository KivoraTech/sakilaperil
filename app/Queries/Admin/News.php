<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\NewsCategory;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Admin reads for news & stories (port of sakila/lib/queries/admin/news.ts). */
final class News
{
    /**
     * @param  array{q: string, status: string, page: int, mine?: bool}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $query = NewsPost::query()
            ->when($p['status'] !== '', fn (Builder $b) => $b->where('status', $p['status']))
            ->when($p['q'] !== '', fn (Builder $b) => SharedContent::titleSearch($b, $p['q']))
            ->when($p['mine'] ?? false, fn (Builder $b) => $b->where('authorId', $user->id));
        $total = (clone $query)->count();
        $rows = $query
            ->with([
                'translations:id,postId,locale,title,slug',
                'author:id,name',
                'category.translations' => fn ($q) => $q->where('locale', 'it'),
            ])
            ->orderByDesc('updatedAt')
            ->limit(ContentCommon::PAGE_SIZE)->offset(($p['page'] - 1) * ContentCommon::PAGE_SIZE)
            ->get();
        $mayPublish = ContentCommon::can($user, 'content.publish');

        return [
            'items' => $rows->map(function (NewsPost $r) use ($user, $mayPublish): array {
                $status = $r->status->value;
                $meta = array_values(array_filter([$r->category?->translations->first()?->name, $r->author?->name]));

                return [
                    'id' => $r->id,
                    ...ContentCommon::itTitle($r->translations),
                    'status' => $status,
                    'scheduled' => ContentCommon::isScheduled($status, $r->publishedAt),
                    'locales' => $r->translations->map(fn ($t) => ContentCommon::val($t->locale))->values()->all(),
                    'updatedAt' => ContentCommon::iso($r->updatedAt),
                    'publishedAt' => ContentCommon::iso($r->publishedAt),
                    'meta' => $meta !== [] ? implode(' · ', $meta) : null,
                    'canEdit' => ContentCommon::canEditContent($user, $r->authorId) && ($status === 'DRAFT' || $mayPublish),
                ];
            })->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => ContentCommon::pageCount($total),
        ];
    }

    /** @return array{categories: list<array{value: string, label: string}>, projects: list<array{value: string, label: string}>, authors: list<array{value: string, label: string}>} */
    public static function formOptions(): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $categories = NewsCategory::query()->orderBy('position')
            ->with(['translations' => fn ($q) => $q->where('locale', 'it')])->get()
            ->map(fn (NewsCategory $c): array => ['value' => $c->id, 'label' => (string) ($c->translations->first()->name ?? $c->key)])
            ->values()->all();
        $authors = ContentCommon::can($user, 'content.editAll')
            ? User::query()->where('isActive', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $a): array => ['value' => (string) $a->id, 'label' => (string) $a->name])->values()->all()
            : [];

        return ['categories' => $categories, 'projects' => Projects::options(false), 'authors' => $authors];
    }

    /** @return array<string, mixed>|null NewsForEdit of the reference */
    public static function forEdit(string $id): ?array
    {
        $user = ContentCommon::requireCapability('content.create');
        $r = NewsPost::query()->with([
            'translations',
            'tags.tag.translations' => fn ($q) => $q->where('locale', 'it'),
        ])->find($id);
        if (! $r) {
            return null;
        }
        $it = ContentCommon::byLocale($r->translations, 'it');
        $en = ContentCommon::byLocale($r->translations, 'en');
        $tr = fn ($t): array => [
            'title' => (string) ($t->title ?? ''),
            'slug' => (string) ($t->slug ?? ''),
            'excerpt' => (string) ($t->excerpt ?? ''),
            'body' => ContentCommon::docOrEmpty($t?->body),
            'seoTitle' => (string) ($t->seoTitle ?? ''),
            'seoDescription' => (string) ($t->seoDescription ?? ''),
        ];
        $media = Media::dtosByIds([$r->coverImageId]);
        $status = $r->status->value;

        return [
            'id' => $r->id,
            'status' => $status,
            'authorId' => $r->authorId,
            'values' => [
                'publishedAt' => ContentCommon::isoOrEmpty($r->publishedAt),
                'coverImageId' => $r->coverImageId,
                'categoryId' => $r->categoryId,
                'projectId' => $r->projectId,
                'authorId' => $r->authorId,
                'isFeatured' => (bool) $r->isFeatured,
                'tags' => $r->tags->map(fn ($t) => $t->tag?->translations->first()?->name)->filter()->values()->all(),
                'translations' => ['it' => $tr($it), 'en' => [...$tr($en), 'enabled' => $en !== null]],
            ],
            'coverAsset' => $r->coverImageId ? ($media[$r->coverImageId] ?? null) : null,
            'slugs' => ['it' => $it?->slug, 'en' => $en?->slug],
            'canEdit' => ContentCommon::canEditContent($user, $r->authorId) && ($status === 'DRAFT' || ContentCommon::can($user, 'content.publish')),
        ];
    }

    /**
     * Draft preview: the saved state of a post in the public NewsDetail shape, whatever its status.
     *
     * @return array<string, mixed>|null
     */
    public static function draftForPreview(string $id, string $locale): ?array
    {
        ContentCommon::requireCapability('content.create');
        $r = NewsPost::query()->with([
            'translations', 'coverImage', 'category.translations', 'tags.tag.translations', 'author:id,name',
        ])->find($id);
        if (! $r) {
            return null;
        }
        $t = SharedContent::pickTranslation($r->translations, $locale);
        if ($t === null) {
            return null;
        }
        $ct = $r->category ? SharedContent::pickTranslation($r->category->translations, $locale) : null;
        $alternates = [];
        foreach ($r->translations as $tr) {
            $alternates[ContentCommon::val($tr->locale)] = Routes::news((string) $tr->slug);
        }
        $tags = [];
        foreach ($r->tags as $pt) {
            $tt = $pt->tag ? SharedContent::pickTranslation($pt->tag->translations, $locale) : null;
            if ($tt !== null) {
                $tags[] = ['id' => $pt->tag->id, 'name' => (string) $tt['row']->name, 'slug' => (string) $tt['row']->slug];
            }
        }

        return [
            'status' => $r->status->value,
            'id' => $r->id,
            'slug' => (string) $t['row']->slug,
            'title' => (string) $t['row']->title,
            'excerpt' => $t['row']->excerpt,
            'publishedAt' => ContentCommon::iso($r->publishedAt ?? $r->updatedAt),
            'coverImage' => Assets::toDto($r->coverImage, $locale),
            'category' => $r->category && $ct ? ['id' => $r->category->id, 'key' => $r->category->key, 'name' => (string) $ct['row']->name] : null,
            'isFallback' => $t['isFallback'],
            'body' => is_array($t['row']->body) ? $t['row']->body : null,
            'seoTitle' => $t['row']->seoTitle,
            'seoDescription' => $t['row']->seoDescription,
            'tags' => $tags,
            'projectId' => $r->projectId,
            'authorName' => $r->author?->name,
            'alternates' => $alternates,
        ];
    }
}
