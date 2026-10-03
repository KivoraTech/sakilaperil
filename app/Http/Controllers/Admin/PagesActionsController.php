<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\Permissions;
use App\Blocks\BlockRegistry;
use App\Enums\ContentStatus;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PagePreviewDraft;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\PageTranslation;
use App\Models\User;
use App\Pages\Input;
use App\Pages\Json;
use App\Pages\JsonEncoded;
use App\Pages\PageCache;
use App\Pages\Publish;
use App\Pages\RichTextSanitizer;
use App\Support\Cuid;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Page-builder actions — port of resources/js/lib/actions/pages.ts of the reference (ADR-003).
 * Each action: capability (route middleware) → validate → write (transaction) → audit → cache
 * expiry ONLY when the public output changes (publish / unpublish / delete / live slug or noindex).
 * Drafts (page_sections + translation title/SEO) never touch the public cache.
 */
final class PagesActionsController extends Controller
{
    public const MAX_SECTION_BYTES = 100_000;

    public const PREVIEW_DRAFT_TTL_SECONDS = 24 * 60 * 60;

    // ───────────────────────────── create ─────────────────────────────

    public function create(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $data = Input::schema('pages', 'createPageSchema', Input::body($request));
            $title = Input::text($data->title ?? '');
            $slug = Input::requireSlug($data->slug ?? '');
            $enTitle = Input::text($data->enTitle ?? '');
            $enSlugRaw = mb_strtolower(Input::text($data->enSlug ?? ''));
            $enSlug = null;
            if ($enTitle !== '' || $enSlugRaw !== '') {
                $errors = [];
                if ($enTitle === '') {
                    $errors['enTitle'] = ['Enter the English title'];
                }
                [$enSlug, $slugError] = Input::slug($enSlugRaw);
                if ($slugError !== null) {
                    $errors['enSlug'] = [$slugError];
                }
                if ($errors !== []) {
                    return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', $errors);
                }
            }
            if ($title === '') {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['title' => ['Enter a title']]);
            }
            if ($this->slugTaken('it', $slug)) {
                return ActionResult::fail('CONFLICT', 'This slug is already used.', ['slug' => ['Already used by another page']]);
            }
            if ($enSlug !== null && $this->slugTaken('en', $enSlug)) {
                return ActionResult::fail('CONFLICT', 'This slug is already used.', ['enSlug' => ['Already used by another page']]);
            }

            try {
                $page = DB::transaction(function () use ($user, $title, $slug, $enTitle, $enSlug) {
                    $page = Page::query()->create([
                        'status' => ContentStatus::DRAFT,
                        'createdById' => $user->id,
                        'updatedById' => $user->id,
                    ]);
                    PageTranslation::query()->create(['pageId' => $page->id, 'locale' => 'it', 'title' => $title, 'slug' => $slug]);
                    if ($enSlug !== null) { // validated above: an English slug implies an English title
                        PageTranslation::query()->create(['pageId' => $page->id, 'locale' => 'en', 'title' => $enTitle, 'slug' => $enSlug]);
                    }

                    return $page;
                });
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return ActionResult::fail('CONFLICT', 'This slug is already used.');
                }
                throw $e;
            }

            Audit::log(AuditAction::PAGE_CREATED, $user->id, 'Page', $page->id, [
                'title' => $title, 'slug' => $slug, 'enSlug' => $enSlug,
            ]);

            return ActionResult::ok(['id' => $page->id], 'Page created');
        });
    }

    /** Add a missing language version (e.g. English) to an existing page. */
    /**
     * System pages (home, privacy …) carry a `key` the site relies on; they normally come with the
     * content import. On an installation without it (migrations only) an admin creates them here as
     * empty Italian + English drafts — the regular "New page" dialog cannot (reserved slug, no key).
     *
     * @var array<string, array{it: array{0: string, 1: string}, en: array{0: string, 1: string}}>
     */
    private const SYSTEM_PAGE_DEFAULTS = [
        'home' => ['it' => ['Home', Input::HOME_SLUG], 'en' => ['Home', Input::HOME_SLUG]],
        'about' => ['it' => ['Chi siamo', 'chi-siamo'], 'en' => ['About us', 'about-us']],
        'get-involved' => ['it' => ['Partecipa', 'partecipa'], 'en' => ['Get involved', 'get-involved']],
        'transparency' => ['it' => ['Documenti', 'documenti'], 'en' => ['Documents', 'documents']],
        'contact' => ['it' => ['Contatti', 'contatti'], 'en' => ['Contact', 'contact']],
        'privacy' => ['it' => ['Privacy', 'privacy'], 'en' => ['Privacy', 'privacy']],
        'cookies' => ['it' => ['Cookie', 'cookie'], 'en' => ['Cookies', 'cookies']],
    ];

    public function createSystem(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $body = Json::object(Input::body($request));
            $key = $body->key ?? null;
            if (! is_string($key) || ! isset(self::SYSTEM_PAGE_DEFAULTS[$key])) {
                return ActionResult::fail('VALIDATION', 'Unknown page.', ['key' => ['Invalid option']]);
            }
            $existing = Page::query()->where('key', $key)->value('id');
            if ($existing !== null) {
                return ActionResult::fail('CONFLICT', 'This page already exists.', ['key' => ['Already created']]);
            }
            $defaults = self::SYSTEM_PAGE_DEFAULTS[$key];
            foreach ($defaults as $locale => [, $slug]) {
                if ($this->slugTaken($locale, $slug)) {
                    return ActionResult::fail('CONFLICT', "Another page already uses the address “{$slug}”. Rename it first.", ['key' => ['Address already in use']]);
                }
            }

            try {
                $page = DB::transaction(function () use ($user, $key, $defaults) {
                    $page = Page::query()->create([
                        'key' => $key,
                        'status' => ContentStatus::DRAFT,
                        'createdById' => $user->id,
                        'updatedById' => $user->id,
                    ]);
                    foreach ($defaults as $locale => [$title, $slug]) {
                        PageTranslation::query()->create(['pageId' => $page->id, 'locale' => $locale, 'title' => $title, 'slug' => $slug]);
                    }

                    return $page;
                });
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return ActionResult::fail('CONFLICT', 'This page already exists.');
                }
                throw $e;
            }

            Audit::log(AuditAction::PAGE_CREATED, $user->id, 'Page', $page->id, ['key' => $key, 'system' => true]);

            return ActionResult::ok(['id' => $page->id], 'Page created — add sections and publish it');
        });
    }

    public function createTranslation(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $data = Input::schema('pages', 'createTranslationSchema', Input::body($request));
            $locale = (string) $data->locale;
            $title = Input::text($data->title);
            $slugInput = Input::requireSlug($data->slug);
            $copyFrom = isset($data->copyFrom) ? (string) $data->copyFrom : null;

            $page = Page::query()->with('translations:id,pageId,locale')->find((string) $data->pageId);
            if ($page === null) {
                return ActionResult::fail('NOT_FOUND', 'Page not found.');
            }
            if ($page->translations->contains(fn ($t) => $t->locale->value === $locale)) {
                return ActionResult::fail('CONFLICT', 'This language version already exists.');
            }
            $slug = $page->key === 'home' ? Input::HOME_SLUG : $slugInput;
            if ($this->slugTaken($locale, $slug)) {
                return ActionResult::fail('CONFLICT', 'This slug is already used.', ['slug' => ['Already used by another page']]);
            }

            try {
                DB::transaction(function () use ($page, $locale, $title, $slug, $copyFrom, $user): void {
                    PageTranslation::query()->create(['pageId' => $page->id, 'locale' => $locale, 'title' => $title, 'slug' => $slug]);
                    if ($copyFrom !== null && $copyFrom !== $locale) {
                        $this->copySectionRows($page->id, $copyFrom, $locale);
                    }
                    $page->forceFill(['updatedById' => $user->id, 'updatedAt' => self::now()])->save();
                });
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return ActionResult::fail('CONFLICT', 'This slug is already used.');
                }
                throw $e;
            }

            Audit::log(AuditAction::PAGE_CREATED, $user->id, 'Page', $page->id, [
                'locale' => $locale, 'slug' => $slug, 'copiedFrom' => $copyFrom,
            ]);

            return ActionResult::ok(['locale' => $locale], 'Language version created');
        });
    }

    // ───────────────────────────── live preview ─────────────────────────────

    /**
     * Stage the editor's UNSAVED state for the live side-by-side preview (debounced while typing).
     * Validated + sanitised like a save, stored per user+page+locale in a scratch table the public
     * site never reads: no audit entry and no cache expiry.
     */
    public function stagePreview(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $raw = Input::body($request);
            $data = Input::schema('pages', 'stagePreviewSchema', $raw);
            $this->assertSectionSizes($raw->sections ?? []);
            $pageId = (string) $data->pageId;
            $locale = (string) $data->locale;

            $exists = PageTranslation::query()->where('pageId', $pageId)->where('locale', $locale)->exists();
            if (! $exists) {
                return ActionResult::fail('NOT_FOUND', 'This language version does not exist yet.');
            }

            $sections = [];
            foreach ((array) ($raw->sections ?? []) as $s) {
                $type = (string) $s->type;
                $sections[] = (object) [
                    'id' => (string) $s->key,
                    'type' => $type,
                    'schemaVersion' => BlockRegistry::isBlockType($type) ? BlockRegistry::version($type) : 1,
                    'isVisible' => (bool) $s->isVisible,
                    'content' => RichTextSanitizer::sanitizeBlockJson(Json::object($s->content ?? null)),
                    'settings' => Json::object($s->settings ?? null),
                ];
            }
            $now = self::now();
            $row = PagePreviewDraft::query()->updateOrCreate(
                ['userId' => $user->id, 'pageId' => $pageId, 'locale' => $locale],
                ['clientId' => (string) $data->clientId, 'title' => Input::text($data->title ?? ''), 'sections' => $sections, 'updatedAt' => $now],
            );

            // Opportunistic cleanup of abandoned previews (cheap: indexed on updatedAt).
            if (random_int(1, 100) <= 5) {
                PagePreviewDraft::query()->where('updatedAt', '<', $now->copy()->subSeconds(self::PREVIEW_DRAFT_TTL_SECONDS))->delete();
            }

            return ActionResult::ok(['stagedAt' => Json::iso($row->updatedAt)]);
        });
    }

    // ───────────────────────────── save draft ─────────────────────────────

    /**
     * Save the draft of one page+locale: title/SEO + the complete ordered section list. Drafts may
     * contain incomplete blocks; they are reported in `issues` and block publishing only.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $raw = Input::body($request);
            $data = Input::schema('pages', 'saveSectionsSchema', $raw);
            $meta = Input::schema('pages', 'pageMetaSchema', (object) [
                ...get_object_vars(Json::object($raw->meta ?? null)),
                'pageId' => $data->pageId,
                'locale' => $data->locale,
            ]);
            $this->assertSectionSizes($raw->sections ?? []);
            $pageId = (string) $data->pageId;
            $locale = (string) $data->locale;
            $expectedVersion = isset($data->expectedVersion) ? (string) $data->expectedVersion : null;
            /** @var list<stdClass> $sections */
            $sections = array_values((array) ($raw->sections ?? []));
            $title = Input::text($meta->title);
            if ($title === '') {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['title' => ['Enter a title']]);
            }

            $translation = PageTranslation::query()->with('page:id,key')->where('pageId', $pageId)->where('locale', $locale)->first();
            if ($translation === null) {
                return ActionResult::fail('NOT_FOUND', 'This language version does not exist yet.');
            }

            $isHome = $translation->page?->key === 'home';
            $slug = $translation->slug;
            $metaSlug = mb_strtolower(Input::text($meta->slug ?? ''));
            if (! $isHome && $metaSlug !== $translation->slug) {
                [$parsed, $error] = Input::slug($metaSlug);
                if ($error !== null) {
                    return ActionResult::fail('VALIDATION', 'Please check the slug.', ['slug' => [$error]]);
                }
                $slug = $parsed;
            }
            $noIndex = (bool) ($meta->noIndex ?? false);
            $isLive = $translation->publishedRevisionId !== null;
            $slugChanged = $slug !== $translation->slug;
            $noIndexChanged = $noIndex !== (bool) $translation->noIndex;
            // Slug and noIndex are read live (URLs, sitemap): on a published page they are publishing decisions.
            if ($isLive && ($slugChanged || $noIndexChanged) && ! Permissions::can($user->role, 'pages.publish')) {
                return ActionResult::fail('FORBIDDEN', 'Only editors and admins can change the address or indexing of a live page.');
            }
            if ($slugChanged && $this->slugTaken($locale, $slug, $pageId)) {
                return ActionResult::fail('CONFLICT', 'This slug is already used.', ['slug' => ['Already used by another page']]);
            }

            try {
                $result = DB::transaction(function () use ($translation, $expectedVersion, $pageId, $locale, $sections, $title, $slug, $meta, $noIndex, $user) {
                    /** @var PageTranslation $current */
                    $current = PageTranslation::query()->whereKey($translation->id)->lockForUpdate()->firstOrFail();
                    if ($expectedVersion !== null && $expectedVersion !== '' && Json::iso($current->updatedAt) !== $expectedVersion) {
                        return null; // conflict
                    }

                    $existingIds = PageSection::query()->where('pageId', $pageId)->where('locale', $locale)->pluck('id')->all();
                    $existing = array_flip($existingIds);
                    $keep = [];
                    foreach ($sections as $s) {
                        $id = isset($s->id) && is_string($s->id) ? $s->id : null;
                        if ($id !== null && isset($existing[$id])) {
                            $keep[$id] = true;
                        }
                    }
                    $toDelete = array_values(array_filter($existingIds, fn ($id) => ! isset($keep[$id])));
                    if ($toDelete !== []) {
                        PageSection::query()->whereIn('id', $toDelete)->where('pageId', $pageId)->where('locale', $locale)->delete();
                    }

                    $now = self::now();
                    $sectionIds = [];
                    foreach ($sections as $position => $s) {
                        $type = (string) $s->type;
                        $values = [
                            'type' => $type,
                            'schemaVersion' => BlockRegistry::isBlockType($type) ? BlockRegistry::version($type) : 1,
                            'position' => $position,
                            'isVisible' => (bool) $s->isVisible,
                            'content' => JsonEncoded::of(RichTextSanitizer::sanitizeBlockJson(Json::object($s->content ?? null))),
                            'settings' => JsonEncoded::of(Json::object($s->settings ?? null)),
                            'updatedAt' => $now,
                        ];
                        $id = isset($s->id) && is_string($s->id) ? $s->id : null;
                        if ($id !== null && isset($keep[$id])) {
                            DB::table('page_sections')->where('id', $id)->update(self::rowValues($values));
                            $sectionIds[] = $id;
                        } else {
                            $newId = Cuid::make();
                            DB::table('page_sections')->insert(self::rowValues([
                                ...$values, 'id' => $newId, 'pageId' => $pageId, 'locale' => $locale, 'createdAt' => $now,
                            ]));
                            $sectionIds[] = $newId;
                        }
                    }

                    $current->forceFill([
                        'title' => $title,
                        'slug' => $slug,
                        'seoTitle' => Input::optionalText($meta->seoTitle ?? null),
                        'seoDescription' => Input::optionalText($meta->seoDescription ?? null),
                        'ogImageId' => Input::optionalText($meta->ogImageId ?? null),
                        'noIndex' => $noIndex,
                        'hasDraftChanges' => true,
                        'updatedAt' => $now,
                    ])->save();
                    Page::query()->whereKey($pageId)->update(['updatedById' => $user->id, 'updatedAt' => $now->format('Y-m-d H:i:s.v')]);

                    return ['sectionIds' => $sectionIds, 'version' => Json::iso($now)];
                });
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return ActionResult::fail('CONFLICT', 'This slug is already used.');
                }
                throw $e;
            }
            if ($result === null) {
                return ActionResult::fail('CONFLICT', 'Someone else saved this page while you were editing. Reload to see their changes (copy your work first).');
            }

            $rows = [];
            foreach ($sections as $i => $s) {
                $type = (string) $s->type;
                $rows[] = [
                    'id' => $result['sectionIds'][$i],
                    'type' => $type,
                    'schemaVersion' => BlockRegistry::isBlockType($type) ? BlockRegistry::version($type) : 1,
                    'isVisible' => (bool) $s->isVisible,
                    'position' => $i,
                    'content' => $s->content ?? null,
                    'settings' => $s->settings ?? new stdClass,
                ];
            }
            $issues = Publish::validateDraftSections($rows);

            Audit::log(AuditAction::PAGE_UPDATED, $user->id, 'Page', $pageId, [
                'locale' => $locale, 'sections' => count($sections), 'slugChanged' => $slugChanged, 'slug' => $slug,
            ]);
            // A published page's URL / indexing changed: the live site must follow immediately.
            if ($isLive && ($slugChanged || $noIndexChanged)) {
                PageCache::expirePage($pageId);
            }

            return ActionResult::ok(
                [...$result, 'issues' => $issues],
                $issues !== [] ? 'Draft saved — some sections need attention' : 'Draft saved',
            );
        });
    }

    // ───────────────────────────── publish / unpublish ─────────────────────────────

    public function publish(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $data = Input::schema('pages', 'publishPageSchema', Input::body($request));
            $pageId = (string) $data->pageId;
            $locale = (string) $data->locale;
            $confirmEmpty = (bool) ($data->confirmEmpty ?? false);

            $outcome = DB::transaction(function () use ($pageId, $locale, $user, $confirmEmpty) {
                /** @var PageTranslation|null $translation */
                $translation = PageTranslation::query()->where('pageId', $pageId)->where('locale', $locale)->lockForUpdate()->first();
                if ($translation === null) {
                    return ['notFound' => true];
                }
                $rows = PageSection::query()->where('pageId', $pageId)->where('locale', $locale)->orderBy('position')->get()
                    ->map(fn (PageSection $s) => self::sectionRow($s))->all();
                $prepared = Publish::prepare([
                    'title' => $translation->title,
                    'seoTitle' => $translation->seoTitle,
                    'seoDescription' => $translation->seoDescription,
                    'ogImageId' => $translation->ogImageId,
                    'noIndex' => (bool) $translation->noIndex,
                    'sections' => $rows,
                ]);
                if (! $prepared['ok']) {
                    return $prepared;
                }
                // An empty page is almost always an accident (all sections deleted or hidden). The home
                // page can never go live empty; any other page needs an explicit confirmation.
                if ($prepared['sectionCount'] === 0) {
                    $isHome = Page::query()->whereKey($pageId)->value('key') === 'home';
                    if ($isHome || ! $confirmEmpty) {
                        return ['empty' => $isHome ? 'home' : 'confirm'];
                    }
                }
                $now = self::now();
                $revisionId = Cuid::make();
                DB::table('page_revisions')->insert([
                    'id' => $revisionId,
                    'pageId' => $pageId,
                    'locale' => $locale,
                    'snapshot' => JsonEncoded::of($prepared['snapshot'])->json,
                    'createdById' => $user->id,
                    'createdAt' => $now->format('Y-m-d H:i:s.v'),
                ]);
                $translation->forceFill([
                    'publishedRevisionId' => $revisionId,
                    'publishedAt' => $now,
                    'hasDraftChanges' => false,
                    'updatedAt' => $now,
                ])->save();
                Page::query()->whereKey($pageId)->update([
                    'status' => ContentStatus::PUBLISHED->value,
                    'updatedById' => $user->id,
                    'updatedAt' => $now->format('Y-m-d H:i:s.v'),
                ]);

                return ['ok' => true, 'revisionId' => $revisionId, 'publishedAt' => $now, 'sectionCount' => $prepared['sectionCount']];
            });

            if (isset($outcome['notFound'])) {
                return ActionResult::fail('NOT_FOUND', 'This language version does not exist.');
            }
            if (isset($outcome['empty'])) {
                return $outcome['empty'] === 'home'
                    ? ActionResult::fail('VALIDATION', 'The home page needs at least one visible section before it can be published.', ['sections' => ['EMPTY_HOME']])
                    : ActionResult::fail('VALIDATION', 'This page has no visible sections. Confirm to publish it empty.', ['sections' => ['EMPTY_PAGE']]);
            }
            if (! $outcome['ok']) {
                $fieldErrors = [];
                foreach ($outcome['issues'] as $issue) {
                    $fieldErrors['section:'.$issue['sectionId']] = $issue['messages'];
                }

                return ActionResult::fail('VALIDATION', 'Fix these sections before publishing. '.Publish::summarise($outcome['issues']), $fieldErrors);
            }

            Audit::log(AuditAction::PAGE_PUBLISHED, $user->id, 'Page', $pageId, [
                'locale' => $locale, 'revisionId' => $outcome['revisionId'], 'sections' => $outcome['sectionCount'],
            ]);
            PageCache::expirePage($pageId);

            return ActionResult::ok(['publishedAt' => Json::iso($outcome['publishedAt']), 'revisionId' => $outcome['revisionId']], 'Published');
        });
    }

    public function unpublish(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $data = Input::schema('pages', 'pageLocaleSchema', Input::body($request));
            $pageId = (string) $data->pageId;
            $locale = (string) $data->locale;
            $page = Page::query()->with('translations')->find($pageId);
            if ($page === null) {
                return ActionResult::fail('NOT_FOUND', 'Page not found.');
            }
            if ($page->key === 'home') {
                return ActionResult::fail('FORBIDDEN', 'The home page cannot be unpublished.');
            }
            $t = $page->translations->first(fn ($x) => $x->locale->value === $locale);
            if ($t === null || $t->publishedRevisionId === null) {
                return ActionResult::fail('CONFLICT', 'This language version is not published.');
            }
            $stillLive = $page->translations->contains(fn ($x) => $x->locale->value !== $locale && $x->publishedRevisionId !== null);

            DB::transaction(function () use ($t, $page, $stillLive, $user): void {
                $now = self::now();
                $t->forceFill(['publishedRevisionId' => null, 'publishedAt' => null, 'hasDraftChanges' => true, 'updatedAt' => $now])->save();
                $page->forceFill([
                    'status' => $stillLive ? ContentStatus::PUBLISHED : ContentStatus::DRAFT,
                    'updatedById' => $user->id,
                    'updatedAt' => $now,
                ])->save();
            });
            Audit::log(AuditAction::PAGE_UNPUBLISHED, $user->id, 'Page', $pageId, ['locale' => $locale]);
            PageCache::expirePage($pageId);

            return ActionResult::ok(null, 'Unpublished — the page is no longer visible on the website');
        });
    }

    // ───────────────────────────── discard / restore ─────────────────────────────

    /**
     * Replace the draft with a published revision (default: the live one) — "Discard changes" /
     * "Restore this version". Does not change what is live.
     */
    public function restore(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $body = Json::object(Input::body($request));
            $data = Input::schema('pages', 'pageLocaleSchema', $body);
            $revisionId = isset($body->revisionId) && is_string($body->revisionId) && $body->revisionId !== '' ? mb_substr($body->revisionId, 0, 64) : null;
            $pageId = (string) $data->pageId;
            $locale = (string) $data->locale;
            $translation = PageTranslation::query()->where('pageId', $pageId)->where('locale', $locale)->first();
            if ($translation === null) {
                return ActionResult::fail('NOT_FOUND', 'This language version does not exist.');
            }
            $targetId = $revisionId ?? $translation->publishedRevisionId;
            if ($targetId === null) {
                return ActionResult::fail('CONFLICT', 'There is no published version to restore.');
            }
            $revision = PageRevision::query()->whereKey($targetId)->where('pageId', $pageId)->where('locale', $locale)->first();
            if ($revision === null) {
                return ActionResult::fail('NOT_FOUND', 'Version not found.');
            }
            $snapshot = Json::column($revision, 'snapshot');
            if (! $snapshot instanceof stdClass || ! is_array($snapshot->sections ?? null) || ! is_string($snapshot->title ?? null)) {
                return ActionResult::fail('ERROR', 'Something went wrong. Please try again.');
            }

            DB::transaction(function () use ($pageId, $locale, $snapshot, $translation, $targetId): void {
                $now = self::now();
                PageSection::query()->where('pageId', $pageId)->where('locale', $locale)->delete();
                $rows = [];
                foreach (array_values($snapshot->sections) as $position => $s) {
                    $rows[] = self::rowValues([
                        'id' => Cuid::make(),
                        'pageId' => $pageId,
                        'locale' => $locale,
                        'type' => (string) $s->type,
                        'schemaVersion' => (int) $s->schemaVersion,
                        'position' => $position,
                        'isVisible' => (bool) ($s->isVisible ?? true),
                        'content' => JsonEncoded::of($s->content ?? new stdClass),
                        'settings' => JsonEncoded::of(Json::object($s->settings ?? null)),
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]);
                }
                if ($rows !== []) {
                    DB::table('page_sections')->insert($rows);
                }
                $translation->forceFill([
                    'title' => $snapshot->title,
                    'seoTitle' => $snapshot->seoTitle ?? null,
                    'seoDescription' => $snapshot->seoDescription ?? null,
                    'ogImageId' => $snapshot->ogImageId ?? null,
                    'hasDraftChanges' => $targetId !== $translation->publishedRevisionId,
                    'updatedAt' => $now,
                ])->save();
            });
            Audit::log(AuditAction::PAGE_REVERTED, $user->id, 'Page', $pageId, ['locale' => $locale, 'revisionId' => $targetId]);

            return ActionResult::ok(null, 'Draft restored');
        });
    }

    /** Replace the draft sections of `to` with a copy of `from` (ADR-007 "copy from Italian"). */
    public function copySections(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $body = Json::object(Input::body($request));
            $pageId = Input::schema('pages', 'deletePageSchema', (object) ['pageId' => $body->pageId ?? null])->pageId;
            $from = $body->from ?? null;
            $to = $body->to ?? null;
            if (! in_array($from, ['it', 'en'], true) || ! in_array($to, ['it', 'en'], true)) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['from' => ['Invalid option']]);
            }
            if ($from === $to) {
                return ActionResult::fail('VALIDATION', 'Choose a different language to copy from.');
            }
            $target = PageTranslation::query()->where('pageId', $pageId)->where('locale', $to)->first();
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'Create this language version first.');
            }
            $count = DB::transaction(function () use ($pageId, $from, $to, $target): int {
                PageSection::query()->where('pageId', $pageId)->where('locale', $to)->delete();
                $count = $this->copySectionRows((string) $pageId, $from, $to);
                $target->forceFill(['hasDraftChanges' => true, 'updatedAt' => self::now()])->save();

                return $count;
            });
            Audit::log(AuditAction::PAGE_UPDATED, $user->id, 'Page', (string) $pageId, [
                'copiedFrom' => $from, 'locale' => $to, 'sections' => $count,
            ]);

            return ActionResult::ok(['count' => $count], "Copied {$count} sections");
        });
    }

    // ───────────────────────────── delete ─────────────────────────────

    public function delete(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = $this->user($request);
            $data = Input::schema('pages', 'deletePageSchema', Input::body($request));
            $pageId = (string) $data->pageId;
            $page = Page::query()->with('translations:id,pageId,locale,title,slug')->find($pageId);
            if ($page === null) {
                return ActionResult::fail('NOT_FOUND', 'Page not found.');
            }
            if ($page->key !== null) {
                return ActionResult::fail('FORBIDDEN', 'System pages (home, about, contact…) cannot be deleted. Unpublish or edit them instead.');
            }
            $translations = $page->translations->map(fn ($t) => ['locale' => $t->locale->value, 'title' => $t->title, 'slug' => $t->slug])->values()->all();
            DB::transaction(function () use ($page): void {
                // published revisions are referenced by the translations; clear the pointer first.
                PageTranslation::query()->where('pageId', $page->id)->update(['publishedRevisionId' => null]);
                $page->delete();
            });
            Audit::log(AuditAction::PAGE_DELETED, $user->id, 'Page', $pageId, ['translations' => $translations]);
            PageCache::expirePage($pageId);

            return ActionResult::ok(null, 'Page deleted');
        });
    }

    // ───────────────────────────── helpers ─────────────────────────────

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** Current time truncated to milliseconds (DATETIME(3) precision, Date#toISOString parity). */
    public static function now(): Carbon
    {
        $now = Carbon::now('UTC');
        $now->setMicrosecond(intdiv($now->microsecond, 1000) * 1000);

        return $now;
    }

    private function slugTaken(string $locale, string $slug, ?string $exceptPageId = null): bool
    {
        return PageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->when($exceptPageId !== null, fn ($q) => $q->where('pageId', '!=', $exceptPageId))
            ->exists();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }

    /** @param iterable<mixed> $sections */
    private function assertSectionSizes(iterable $sections): void
    {
        foreach ($sections as $i => $s) {
            if (! $s instanceof stdClass) {
                continue;
            }
            foreach (['content', 'settings'] as $field) {
                if (isset($s->{$field}) && Json::length($s->{$field}) > self::MAX_SECTION_BYTES) {
                    throw Input::invalid([['path' => ['sections', (string) $i, $field], 'message' => 'This section is too large']]);
                }
            }
        }
    }

    /** Copy draft section rows between locales, JSON copied byte-for-byte. Returns the count. */
    private function copySectionRows(string $pageId, string $from, string $to): int
    {
        $source = DB::table('page_sections')->where('pageId', $pageId)->where('locale', $from)->orderBy('position')->get();
        $now = self::now()->format('Y-m-d H:i:s.v');
        $rows = [];
        foreach ($source->values() as $position => $s) {
            $rows[] = [
                'id' => Cuid::make(),
                'pageId' => $pageId,
                'locale' => $to,
                'type' => $s->type,
                'schemaVersion' => $s->schemaVersion,
                'position' => $position,
                'isVisible' => $s->isVisible,
                'content' => $s->content,
                'settings' => $s->settings,
                'createdAt' => $now,
                'updatedAt' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('page_sections')->insert($rows);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function rowValues(array $values): array
    {
        foreach ($values as $k => $v) {
            if ($v instanceof JsonEncoded) {
                $values[$k] = $v->json;
            } elseif ($v instanceof Carbon) {
                $values[$k] = $v->format('Y-m-d H:i:s.v');
            }
        }

        return $values;
    }

    /** @return array<string, mixed> */
    public static function sectionRow(PageSection $s): array
    {
        return [
            'id' => $s->id,
            'type' => $s->type,
            'schemaVersion' => $s->schemaVersion,
            'isVisible' => (bool) $s->isVisible,
            'position' => $s->position,
            'content' => Json::column($s, 'content'),
            'settings' => Json::column($s, 'settings') ?? new stdClass,
        ];
    }
}
