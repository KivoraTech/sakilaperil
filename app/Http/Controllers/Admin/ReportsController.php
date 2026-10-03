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
use App\Models\Report;
use App\Models\ReportTranslation;
use App\Models\User;
use App\Queries\Admin\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Transparency reports (port of app/admin/(shell)/reports/** and lib/actions/reports.ts): annual
 * reports / financial statements as PDFs. No owner field: managing reports requires content.editAll.
 * A PUBLISHED report must have a PDF.
 */
final class ReportsController
{
    /** @return list<string> */
    private static function tagsFor(): array
    {
        return [CacheTags::REPORTS, CacheTags::PAGES];
    }

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $params = ContentCommon::listParams($request->query());

        return Inertia::render('admin/reports/index', [
            'params' => $params,
            'list' => Reports::list($params),
            'canManage' => ContentCommon::can($user, 'content.editAll'),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/reports/edit', [
            'id' => null,
            'status' => null,
            'initialValues' => null,
            'assets' => (object) [],
            'perms' => ['canEdit' => true, 'canPublish' => ContentCommon::can($user, 'content.publish'), 'canDelete' => false],
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $report = Reports::forEdit($id);
        if ($report === null) {
            return AdminNotFound::response($request, 'report', '/admin/reports', 'Back to reports');
        }

        return Inertia::render('admin/reports/edit', [
            'id' => $report['id'],
            'status' => $report['status'],
            'initialValues' => $report['values'],
            'assets' => $report['assets'],
            'perms' => [
                'canEdit' => true,
                'canPublish' => ContentCommon::can($user, 'content.publish'),
                'canDelete' => ContentCommon::can($user, 'content.delete'),
            ],
        ]);
    }

    /** saveReportAction(id, input, intent) */
    public function save(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.editAll');
            $intent = ContentForms::intent(ContentCommon::body($request, 'intent'));
            $rawId = ContentCommon::body($request, 'id');
            $reportId = $rawId ? ContentForms::id($rawId) : null;
            $v = ContentForms::report(ContentCommon::body($request, 'input'));
            if ($intent === 'publish' && ! ContentCommon::can($user, 'content.publish')) {
                ContentCommon::fail('FORBIDDEN', 'Only editors can publish.');
            }

            $existing = $reportId ? Report::query()->find($reportId, ['id', 'status', 'publishedAt']) : null;
            if ($reportId && ! $existing) {
                ContentCommon::notFound('This report no longer exists.');
            }

            $tr = $v['translations'];
            ContentCommon::throwIfErrors(ContentCommon::assertMediaKinds([
                ['id' => $v['fileId'], 'kind' => 'DOCUMENT', 'field' => 'fileId'],
                ['id' => $v['coverImageId'], 'kind' => 'IMAGE', 'field' => 'coverImageId'],
                ['id' => $tr['en']['enabled'] ? $tr['en']['fileId'] : null, 'kind' => 'DOCUMENT', 'field' => 'translations.en.fileId'],
            ]));

            $existingStatus = $existing ? ContentCommon::val($existing->status) : null;
            $status = $intent === 'publish' ? 'PUBLISHED' : ($existingStatus ?? 'DRAFT');
            if ($status === 'PUBLISHED') {
                if ($v['fileId'] === null) {
                    ContentCommon::fail('VALIDATION', 'Attach the PDF before publishing.', ['fileId' => ['A published report needs a PDF']]);
                }
                $missing = ContentCommon::imagesMissingAlt([$v['coverImageId']]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }

            $data = [
                'status' => $status,
                'type' => $v['type'],
                'year' => $v['year'],
                'fileId' => $v['fileId'],
                'coverImageId' => $v['coverImageId'],
                'position' => $v['position'],
                'publishedAt' => ContentSave::publishedAt($v['publishedAt'], $status, $existing?->publishedAt),
            ];

            $savedId = DB::transaction(function () use ($reportId, $data, $tr): string {
                $report = $reportId ? tap(Report::query()->findOrFail($reportId))->update($data) : Report::query()->create($data);
                $rows = [['locale' => 'it', 'fields' => [
                    'title' => $tr['it']['title'], 'description' => ContentCommon::blank($tr['it']['description']), 'fileId' => null,
                ]]];
                if ($tr['en']['enabled']) {
                    $rows[] = ['locale' => 'en', 'fields' => [
                        'title' => $tr['en']['title'], 'description' => ContentCommon::blank($tr['en']['description']), 'fileId' => $tr['en']['fileId'],
                    ]];
                }
                ContentSave::upsertTranslations(ReportTranslation::class, 'reportId', $report->id, $rows, (bool) $tr['en']['enabled']);

                return (string) $report->id;
            });

            Audit::log($reportId ? AuditAction::CONTENT_UPDATED : AuditAction::CONTENT_CREATED, (string) $user->id, 'Report', $savedId, [
                'title' => $tr['it']['title'], 'year' => $v['year'], 'status' => $status,
            ]);
            if ($status === 'PUBLISHED' && $existingStatus !== 'PUBLISHED') {
                Audit::log(AuditAction::CONTENT_PUBLISHED, (string) $user->id, 'Report', $savedId);
            }
            ContentCommon::expireTags(...self::tagsFor());

            return ActionResult::ok(['id' => $savedId], ContentCommon::savedMessage($status, $data['publishedAt']));
        });
    }

    /** setReportStatusAction(id, status) */
    public function setStatus(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.publish');
            $reportId = ContentForms::id(ContentCommon::body($request, 'id'));
            $status = ContentForms::status(ContentCommon::body($request, 'status'));
            $report = Report::query()->find($reportId);
            if (! $report) {
                ContentCommon::notFound('This report no longer exists.');
            }
            if ($status === 'PUBLISHED') {
                if (! $report->fileId) {
                    ContentCommon::fail('VALIDATION', 'Attach the PDF before publishing.');
                }
                $missing = ContentCommon::imagesMissingAlt([$report->coverImageId]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }
            $from = ContentCommon::val($report->status);
            $report->update(['status' => $status] + ($status === 'PUBLISHED' && ! $report->publishedAt ? ['publishedAt' => now()] : []));
            Audit::log(ContentCommon::statusAuditAction($status), (string) $user->id, 'Report', $reportId, ['from' => $from, 'to' => $status]);
            ContentCommon::expireTags(...self::tagsFor());

            return ActionResult::ok(['id' => $reportId], ContentCommon::statusMessage($status, $report->publishedAt));
        });
    }

    /** deleteReportAction(id) */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.delete');
            $reportId = ContentForms::id(ContentCommon::body($request, 'id'));
            $report = Report::query()->with(['translations' => fn ($q) => $q->where('locale', 'it')])->find($reportId);
            if (! $report) {
                ContentCommon::notFound('This report no longer exists.');
            }
            $report->delete();
            Audit::log(AuditAction::CONTENT_DELETED, (string) $user->id, 'Report', $reportId, [
                'title' => $report->translations->first()?->title, 'year' => $report->year, 'status' => ContentCommon::val($report->status),
            ]);
            ContentCommon::expireTags(...self::tagsFor());

            return ActionResult::ok(['id' => $reportId], 'Report deleted (the PDF stays in the media library)');
        });
    }
}
