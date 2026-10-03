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
use App\Content\Input;
use App\Http\Actions\ActionResult;
use App\I18n\Routes;
use App\Models\NewsCategory;
use App\Models\NewsCategoryTranslation;
use App\Models\NewsPost;
use App\Models\NewsPostTag;
use App\Models\NewsPostTranslation;
use App\Models\NewsTag;
use App\Models\NewsTagTranslation;
use App\Models\Project;
use App\Models\User;
use App\Queries\Admin\News;
use App\RichText\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * News & stories (port of app/admin/(shell)/news/** and lib/actions/news.ts). Authors
 * (content.create + content.editOwn) may create posts and edit their OWN posts while they are not
 * published; they can never publish. Editors+ edit everything and publish.
 * Every action: validate → capability → write → audit → expire cache tags.
 */
final class NewsController
{
    /** @return list<string> */
    private static function tagsFor(string $id): array
    {
        return [CacheTags::NEWS, CacheTags::newsPost($id)];
    }

    // ── Pages ────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $params = ContentCommon::listParams($request->query());
        $mine = $request->query('mine') === '1';

        return Inertia::render('admin/news/index', [
            'params' => $params,
            'mine' => $mine,
            'list' => News::list([...$params, 'mine' => $mine]),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/news/edit', [
            'id' => null,
            'status' => null,
            'initialValues' => null,
            'authorId' => (string) $user->id,
            'coverAsset' => null,
            'options' => News::formOptions(),
            'perms' => ['canEdit' => true, 'canPublish' => ContentCommon::can($user, 'content.publish'), 'canDelete' => false],
            'canManageCategories' => ContentCommon::can($user, 'content.editAll'),
            'publicSlug' => null,
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $post = News::forEdit($id);
        if ($post === null) {
            return AdminNotFound::response($request, 'news story', '/admin/news', 'Back to news');
        }

        return Inertia::render('admin/news/edit', [
            'id' => $post['id'],
            'status' => $post['status'],
            'initialValues' => $post['values'],
            'authorId' => null,
            'coverAsset' => $post['coverAsset'],
            'options' => News::formOptions(),
            'perms' => [
                'canEdit' => $post['canEdit'],
                'canPublish' => ContentCommon::can($user, 'content.publish'),
                'canDelete' => ContentCommon::can($user, 'content.delete'),
            ],
            'canManageCategories' => ContentCommon::can($user, 'content.editAll'),
            'publicSlug' => $post['slugs']['it'],
        ]);
    }

    // ── Actions (/admin/actions/news/*) ──────────────────────────────

    /** saveNewsAction(id, input, intent) */
    public function save(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $rawId = ContentCommon::body($request, 'id');
            $user = ContentCommon::requireCapability($rawId ? 'content.editOwn' : 'content.create');
            $intent = ContentForms::intent(ContentCommon::body($request, 'intent'));
            $postId = $rawId ? ContentForms::id($rawId) : null;
            $v = ContentForms::news(ContentCommon::body($request, 'input'));
            $mayPublish = ContentCommon::can($user, 'content.publish');
            if ($intent === 'publish' && ! $mayPublish) {
                ContentCommon::fail('FORBIDDEN', 'Only editors can publish.');
            }

            $existing = $postId ? NewsPost::query()->find($postId, ['id', 'authorId', 'status', 'publishedAt']) : null;
            if ($postId && ! $existing) {
                ContentCommon::notFound('This post no longer exists.');
            }
            if ($existing) {
                if (! ContentCommon::canEditContent($user, $existing->authorId)) {
                    ContentCommon::fail('FORBIDDEN', 'You can only edit your own posts.');
                }
                if (ContentCommon::val($existing->status) !== 'DRAFT' && ! $mayPublish) {
                    ContentCommon::fail('FORBIDDEN', 'This post is published or archived. Ask an editor to change it.');
                }
            }

            $tr = $v['translations'];
            $locales = ContentSave::locales($tr);
            $conflicts = ContentCommon::slugConflicts('news', $locales, $postId);
            if ($conflicts !== []) {
                ContentCommon::slugConflict($conflicts);
            }
            ContentCommon::throwIfErrors([
                ...ContentCommon::assertMediaKinds([
                    ['id' => $v['coverImageId'], 'kind' => 'IMAGE', 'field' => 'coverImageId'],
                ]),
                ...ContentCommon::assertReferences([
                    ['id' => $v['categoryId'], 'model' => NewsCategory::class, 'field' => 'categoryId', 'label' => 'category'],
                    ['id' => $v['projectId'], 'model' => Project::class, 'field' => 'projectId', 'label' => 'project'],
                    ['id' => $v['authorId'], 'model' => User::class, 'field' => 'authorId', 'label' => 'author'],
                ]),
            ]);

            $existingStatus = $existing ? ContentCommon::val($existing->status) : null;
            $status = $intent === 'publish' ? 'PUBLISHED' : ($existingStatus ?? 'DRAFT');
            $bodies = ContentSave::bodies($locales);
            if ($status === 'PUBLISHED') {
                $missing = ContentCommon::imagesMissingAlt([$v['coverImageId'], ...ContentSave::bodyAssetIds($bodies)]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }

            $canAssignAuthor = ContentCommon::can($user, 'content.editAll');
            $authorId = $canAssignAuthor
                ? ($v['authorId'] ?? $existing->authorId ?? (string) $user->id)
                : ($existing->authorId ?? (string) $user->id);

            $data = [
                'status' => $status,
                'publishedAt' => ContentSave::publishedAt($v['publishedAt'], $status, $existing?->publishedAt),
                'coverImageId' => $v['coverImageId'],
                'categoryId' => $v['categoryId'],
                'projectId' => $v['projectId'],
                'authorId' => $authorId,
                'isFeatured' => $v['isFeatured'],
            ];

            $savedId = DB::transaction(function () use ($postId, $data, $locales, $bodies, $tr, $v): string {
                $post = $postId ? tap(NewsPost::query()->findOrFail($postId))->update($data) : NewsPost::query()->create($data);
                $rows = [];
                foreach ($locales as $i => $l) {
                    $rows[] = ['locale' => $l['locale'], 'fields' => [
                        'title' => $l['title'],
                        'slug' => $l['slug'],
                        'excerpt' => ContentCommon::blank($l['excerpt']),
                        'body' => $bodies[$i],
                        'seoTitle' => ContentCommon::blank($l['seoTitle']),
                        'seoDescription' => ContentCommon::blank($l['seoDescription']),
                    ]];
                }
                ContentSave::upsertTranslations(NewsPostTranslation::class, 'postId', $post->id, $rows, (bool) $tr['en']['enabled']);
                $tagIds = self::resolveTagIds($v['tags']);
                NewsPostTag::query()->where('postId', $post->id)->delete();
                foreach ($tagIds as $tagId) {
                    NewsPostTag::query()->create(['postId' => $post->id, 'tagId' => $tagId]);
                }

                return (string) $post->id;
            });

            Audit::log($postId ? AuditAction::CONTENT_UPDATED : AuditAction::CONTENT_CREATED, (string) $user->id, 'NewsPost', $savedId, [
                'title' => $tr['it']['title'], 'status' => $status, 'locales' => array_column($locales, 'locale'),
            ]);
            if ($status === 'PUBLISHED' && $existingStatus !== 'PUBLISHED') {
                Audit::log(AuditAction::CONTENT_PUBLISHED, (string) $user->id, 'NewsPost', $savedId);
            }
            ContentCommon::expireTags(...self::tagsFor($savedId));

            return ActionResult::ok(['id' => $savedId], ContentCommon::savedMessage($status, $data['publishedAt']));
        }, slugConflictOnUnique: true);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function resolveTagIds(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $slug = Routes::slugify($name);
            if ($slug === '') {
                continue;
            }
            $existing = NewsTagTranslation::query()->where('locale', 'it')->where('slug', $slug)->value('tagId');
            if ($existing) {
                $ids[] = (string) $existing;
            } else {
                $tag = NewsTag::query()->create([]);
                NewsTagTranslation::query()->create(['tagId' => $tag->id, 'locale' => 'it', 'name' => $name, 'slug' => $slug]);
                $ids[] = (string) $tag->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** setNewsStatusAction(id, status) */
    public function setStatus(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.publish');
            $postId = ContentForms::id(ContentCommon::body($request, 'id'));
            $status = ContentForms::status(ContentCommon::body($request, 'status'));
            $post = NewsPost::query()->with('translations:id,postId,body,title,locale')->find($postId);
            if (! $post) {
                ContentCommon::notFound('This post no longer exists.');
            }
            if ($status === 'PUBLISHED') {
                $ids = [$post->coverImageId];
                foreach ($post->translations as $t) {
                    array_push($ids, ...RichText::collectAssetIds($t->body));
                }
                $missing = ContentCommon::imagesMissingAlt($ids);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }
            $from = ContentCommon::val($post->status);
            $post->update(['status' => $status] + ($status === 'PUBLISHED' && ! $post->publishedAt ? ['publishedAt' => now()] : []));
            Audit::log(ContentCommon::statusAuditAction($status), (string) $user->id, 'NewsPost', $postId, ['from' => $from, 'to' => $status]);
            ContentCommon::expireTags(...self::tagsFor($postId));

            return ActionResult::ok(['id' => $postId], ContentCommon::statusMessage($status, $post->publishedAt));
        });
    }

    /** deleteNewsAction(id) */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.delete');
            $postId = ContentForms::id(ContentCommon::body($request, 'id'));
            $post = NewsPost::query()->with(['translations' => fn ($q) => $q->where('locale', 'it')])->find($postId);
            if (! $post) {
                ContentCommon::notFound('This post no longer exists.');
            }
            $post->delete();
            Audit::log(AuditAction::CONTENT_DELETED, (string) $user->id, 'NewsPost', $postId, [
                'title' => $post->translations->first()?->title, 'status' => ContentCommon::val($post->status),
            ]);
            ContentCommon::expireTags(...self::tagsFor($postId));

            return ActionResult::ok(['id' => $postId], 'Post deleted');
        });
    }

    /** createNewsCategoryAction({ nameIt, nameEn }) — editors+. Returns the new id for the form's select. */
    public function createCategory(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.editAll');
            $in = Input::from(ContentCommon::body($request, 'input'));
            $nameIt = $in->str('nameIt', 60, true, 'Too small: expected string to have >=1 characters');
            $nameEn = $in->str('nameEn', 60);
            $in->validate();
            $slugIt = Routes::slugify($nameIt);
            $slugEn = $nameEn !== '' ? Routes::slugify($nameEn) : '';
            if ($slugIt === '') {
                ContentCommon::fail('VALIDATION', 'Enter a name with letters or numbers.', ['nameIt' => ['Invalid name']]);
            }
            $clash = NewsCategoryTranslation::query()
                ->where(fn ($w) => $w->where(fn ($x) => $x->where('locale', 'it')->where('slug', $slugIt))
                    ->when($slugEn !== '', fn ($q) => $q->orWhere(fn ($x) => $x->where('locale', 'en')->where('slug', $slugEn))))
                ->exists();
            if ($clash) {
                ContentCommon::fail('CONFLICT', 'A category with this name already exists.', ['nameIt' => ['Already exists']]);
            }
            try {
                $cat = self::insertCategory($slugIt, $slugEn, $nameIt, $nameEn);
            } catch (\Throwable $e) {
                if (ContentCommon::isUniqueViolation($e)) {
                    ContentCommon::fail('CONFLICT', 'A category with this name already exists.');
                }
                throw $e;
            }
            Audit::log(AuditAction::CONTENT_CREATED, (string) $user->id, 'NewsCategory', (string) $cat->id, ['name' => $nameIt]);
            ContentCommon::expireTags(CacheTags::NEWS);

            return ActionResult::ok(['id' => (string) $cat->id, 'name' => $nameIt], 'Category created');
        });
    }

    private static function insertCategory(string $slugIt, string $slugEn, string $nameIt, string $nameEn): NewsCategory
    {
        return DB::transaction(function () use ($slugIt, $slugEn, $nameIt, $nameEn): NewsCategory {
            $max = (int) NewsCategory::query()->max('position');
            $cat = NewsCategory::query()->create([
                'key' => mb_substr($slugIt.'-'.base_convert((string) (int) floor(microtime(true) * 1000), 10, 36), 0, 60),
                'position' => $max + 1,
            ]);
            NewsCategoryTranslation::query()->create(['categoryId' => $cat->id, 'locale' => 'it', 'name' => $nameIt, 'slug' => $slugIt]);
            if ($slugEn !== '') {
                NewsCategoryTranslation::query()->create(['categoryId' => $cat->id, 'locale' => 'en', 'name' => $nameEn, 'slug' => $slugEn]);
            }

            return $cat;
        });
    }
}
