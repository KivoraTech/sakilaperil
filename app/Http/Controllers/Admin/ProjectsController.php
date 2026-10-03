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
use App\Http\Actions\ActionResult;
use App\Models\Project;
use App\Models\ProjectTranslation;
use App\Models\User;
use App\Queries\Admin\Projects;
use App\RichText\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Projects (port of app/admin/(shell)/projects/** and lib/actions/projects.ts). Authors may create
 * projects and edit their own drafts; editors+ edit all and publish. Projects are also listed by page
 * blocks and linked from news/albums, so `pages` is expired too.
 */
final class ProjectsController
{
    /** @return list<string> */
    private static function tagsFor(string $id): array
    {
        return [CacheTags::PROJECTS, CacheTags::project($id), CacheTags::PAGES];
    }

    public function index(Request $request): Response
    {
        $params = ContentCommon::listParams($request->query());

        return Inertia::render('admin/projects/index', ['params' => $params, 'list' => Projects::list($params)]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/projects/edit', [
            'id' => null,
            'status' => null,
            'initialValues' => null,
            'coverAsset' => null,
            'perms' => ['canEdit' => true, 'canPublish' => ContentCommon::can($user, 'content.publish'), 'canDelete' => false],
            'publicSlug' => null,
            'albums' => [],
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $project = Projects::forEdit($id);
        if ($project === null) {
            return AdminNotFound::response($request, 'project', '/admin/projects', 'Back to projects');
        }

        return Inertia::render('admin/projects/edit', [
            'id' => $project['id'],
            'status' => $project['status'],
            'initialValues' => $project['values'],
            'coverAsset' => $project['coverAsset'],
            'perms' => [
                'canEdit' => $project['canEdit'],
                'canPublish' => ContentCommon::can($user, 'content.publish'),
                'canDelete' => ContentCommon::can($user, 'content.delete'),
            ],
            'publicSlug' => $project['slugs']['it'],
            'albums' => $project['albums'],
        ]);
    }

    /** saveProjectAction(id, input, intent) */
    public function save(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $rawId = ContentCommon::body($request, 'id');
            $user = ContentCommon::requireCapability($rawId ? 'content.editOwn' : 'content.create');
            $intent = ContentForms::intent(ContentCommon::body($request, 'intent'));
            $projectId = $rawId ? ContentForms::id($rawId) : null;
            $v = ContentForms::project(ContentCommon::body($request, 'input'));
            $mayPublish = ContentCommon::can($user, 'content.publish');
            if ($intent === 'publish' && ! $mayPublish) {
                ContentCommon::fail('FORBIDDEN', 'Only editors can publish.');
            }

            $existing = $projectId ? Project::query()->find($projectId, ['id', 'createdById', 'status', 'publishedAt']) : null;
            if ($projectId && ! $existing) {
                ContentCommon::notFound('This project no longer exists.');
            }
            if ($existing) {
                if (! ContentCommon::canEditContent($user, $existing->createdById)) {
                    ContentCommon::fail('FORBIDDEN', 'You can only edit your own projects.');
                }
                if (ContentCommon::val($existing->status) !== 'DRAFT' && ! $mayPublish) {
                    ContentCommon::fail('FORBIDDEN', 'This project is published or archived. Ask an editor to change it.');
                }
            }

            $tr = $v['translations'];
            $locales = ContentSave::locales($tr);
            $conflicts = ContentCommon::slugConflicts('project', $locales, $projectId);
            if ($conflicts !== []) {
                ContentCommon::slugConflict($conflicts);
            }
            ContentCommon::throwIfErrors(ContentCommon::assertMediaKinds([
                ['id' => $v['coverImageId'], 'kind' => 'IMAGE', 'field' => 'coverImageId'],
            ]));
            if ($v['startDate'] !== '' && $v['endDate'] !== '' && strtotime($v['endDate']) < strtotime($v['startDate'])) {
                ContentCommon::throwIfErrors(['endDate' => ['The end must be after the start']]);
            }

            $existingStatus = $existing ? ContentCommon::val($existing->status) : null;
            $status = $intent === 'publish' ? 'PUBLISHED' : ($existingStatus ?? 'DRAFT');
            $bodies = ContentSave::bodies($locales);
            if ($status === 'PUBLISHED') {
                $missing = ContentCommon::imagesMissingAlt([$v['coverImageId'], ...ContentSave::bodyAssetIds($bodies)]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }

            $data = [
                'status' => $status,
                'stage' => $v['stage'],
                'district' => ContentCommon::blank($v['district']),
                'latitude' => $v['latitude'] !== '' ? (float) $v['latitude'] : null,
                'longitude' => $v['longitude'] !== '' ? (float) $v['longitude'] : null,
                'startDate' => ContentCommon::dateOrNull($v['startDate']),
                'endDate' => ContentCommon::dateOrNull($v['endDate']),
                'goalAmount' => ContentForms::toMinorUnits($v['goalAmount']),
                'raisedAmount' => ContentForms::toMinorUnits($v['raisedAmount']),
                'currency' => $v['currency'],
                'coverImageId' => $v['coverImageId'],
                'isFeatured' => $v['isFeatured'],
                'position' => $v['position'],
                'publishedAt' => ContentSave::publishedAt($v['publishedAt'], $status, $existing?->publishedAt),
            ];

            $savedId = DB::transaction(function () use ($projectId, $data, $locales, $bodies, $tr, $user): string {
                $project = $projectId
                    ? tap(Project::query()->findOrFail($projectId))->update($data)
                    : Project::query()->create([...$data, 'createdById' => $user->id]);
                $rows = [];
                foreach ($locales as $i => $l) {
                    $rows[] = ['locale' => $l['locale'], 'fields' => [
                        'title' => $l['title'],
                        'slug' => $l['slug'],
                        'sector' => ContentCommon::blank($l['sector']),
                        'metaLine' => ContentCommon::blank($l['metaLine']),
                        'summary' => ContentCommon::blank($l['summary']),
                        'body' => $bodies[$i],
                        'seoTitle' => ContentCommon::blank($l['seoTitle']),
                        'seoDescription' => ContentCommon::blank($l['seoDescription']),
                    ]];
                }
                ContentSave::upsertTranslations(ProjectTranslation::class, 'projectId', $project->id, $rows, (bool) $tr['en']['enabled']);

                return (string) $project->id;
            });

            Audit::log($projectId ? AuditAction::CONTENT_UPDATED : AuditAction::CONTENT_CREATED, (string) $user->id, 'Project', $savedId, [
                'title' => $tr['it']['title'], 'status' => $status, 'locales' => array_column($locales, 'locale'),
            ]);
            if ($status === 'PUBLISHED' && $existingStatus !== 'PUBLISHED') {
                Audit::log(AuditAction::CONTENT_PUBLISHED, (string) $user->id, 'Project', $savedId);
            }
            ContentCommon::expireTags(...self::tagsFor($savedId));

            return ActionResult::ok(['id' => $savedId], ContentCommon::savedMessage($status, $data['publishedAt']));
        }, slugConflictOnUnique: true);
    }

    /** setProjectStatusAction(id, status) */
    public function setStatus(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.publish');
            $projectId = ContentForms::id(ContentCommon::body($request, 'id'));
            $status = ContentForms::status(ContentCommon::body($request, 'status'));
            $project = Project::query()->with('translations:id,projectId,body')->find($projectId);
            if (! $project) {
                ContentCommon::notFound('This project no longer exists.');
            }
            if ($status === 'PUBLISHED') {
                $ids = [$project->coverImageId];
                foreach ($project->translations as $t) {
                    array_push($ids, ...RichText::collectAssetIds($t->body));
                }
                $missing = ContentCommon::imagesMissingAlt($ids);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }
            $from = ContentCommon::val($project->status);
            $project->update(['status' => $status] + ($status === 'PUBLISHED' && ! $project->publishedAt ? ['publishedAt' => now()] : []));
            Audit::log(ContentCommon::statusAuditAction($status), (string) $user->id, 'Project', $projectId, ['from' => $from, 'to' => $status]);
            ContentCommon::expireTags(...self::tagsFor($projectId));

            return ActionResult::ok(['id' => $projectId], ContentCommon::statusMessage($status, $project->publishedAt));
        });
    }

    /** deleteProjectAction(id) */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.delete');
            $projectId = ContentForms::id(ContentCommon::body($request, 'id'));
            $project = Project::query()->with(['translations' => fn ($q) => $q->where('locale', 'it')])->withCount('donations')->find($projectId);
            if (! $project) {
                ContentCommon::notFound('This project no longer exists.');
            }
            if ((int) $project->getAttribute('donations_count') > 0) {
                ContentCommon::fail('CONFLICT', 'This project has donations linked to it. Archive it instead of deleting it.');
            }
            $project->delete();
            Audit::log(AuditAction::CONTENT_DELETED, (string) $user->id, 'Project', $projectId, [
                'title' => $project->translations->first()?->title, 'status' => ContentCommon::val($project->status),
            ]);
            ContentCommon::expireTags(...[...self::tagsFor($projectId), CacheTags::NEWS, CacheTags::ALBUMS]);

            return ActionResult::ok(['id' => $projectId], 'Project deleted');
        });
    }
}
