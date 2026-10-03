<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Engagement\Forms\Csv;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Queries\Admin\SubmissionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Submissions inbox (ENGAGEMENT; port of app/admin/(shell)/submissions/**). Personal data:
 * `submissions.manage` (ADMIN+) for every read and write. Audit entries never carry personal data.
 */
final class SubmissionsController extends Controller
{
    private const CONTACT_STATUSES = ['NEW', 'READ', 'REPLIED', 'SPAM', 'ARCHIVED'];

    private const APPLICATION_STATUSES = ['NEW', 'IN_REVIEW', 'ACCEPTED', 'REJECTED', 'ARCHIVED'];

    private const EXPORT_COLUMNS = [
        'contact' => ['createdAt', 'status', 'locale', 'name', 'email', 'phone', 'subject', 'message', 'consentAt', 'internalNote'],
        'volunteer' => ['createdAt', 'status', 'locale', 'firstName', 'lastName', 'email', 'phone', 'city', 'country', 'skills', 'availability', 'message', 'consentAt', 'internalNote'],
        'membership' => ['createdAt', 'status', 'locale', 'firstName', 'lastName', 'email', 'phone', 'membershipType', 'address', 'city', 'country', 'message', 'consentAt', 'internalNote'],
        'partner' => ['createdAt', 'status', 'locale', 'organization', 'contactName', 'email', 'phone', 'website', 'partnershipType', 'message', 'consentAt', 'internalNote'],
        'newsletter' => ['createdAt', 'status', 'locale', 'email', 'source', 'provider', 'consentAt', 'confirmedAt', 'unsubscribedAt', 'syncError'],
    ];

    public function index(Request $request): Response
    {
        SubmissionsQuery::authorize($request->user());
        $params = SubmissionsQuery::params($request->query());
        $views = $params['type'] === 'newsletter' ? SubmissionsQuery::NEWSLETTER_VIEWS : SubmissionsQuery::INBOX_VIEWS;
        $view = $params['view'] !== null && in_array($params['view'], $views, true) ? $params['view'] : $views[0];

        return Inertia::render('admin/submissions/index', [
            'params' => [...$params, 'view' => $view],
            'views' => $views,
            'data' => SubmissionsQuery::list([...$params, 'view' => $view]),
            'counts' => SubmissionsQuery::unreadCounts(),
        ]);
    }

    public function show(Request $request, string $type, string $id): Response|HttpResponse
    {
        SubmissionsQuery::authorize($request->user());
        $row = SubmissionsQuery::find($type, $id);
        if ($row === null) {
            return AdminNotFound::response($request, 'message', '/admin/submissions', 'Back to messages');
        }
        $fields = [];
        foreach ($row->getAttributes() as $key => $value) {
            $fields[$key] = $row->getAttribute($key);
        }
        $out = [];
        foreach ($fields as $k => $v) {
            $out[$k] = $v instanceof \DateTimeInterface ? SubmissionsQuery::iso($v) : ($v instanceof \BackedEnum ? $v->value : $v);
        }
        /** @var User|null $handler */
        $handler = $row->getRelation('handledBy');

        return Inertia::render('admin/submissions/show', [
            'type' => $type,
            'row' => $out,
            'handledBy' => $handler ? ['name' => $handler->name] : null,
        ]);
    }

    /** GET /admin/submissions/export?type=…&view=…&q=… — CSV download (ADMIN+), audited. */
    public function export(Request $request): HttpResponse
    {
        $user = SubmissionsQuery::authorize($request->user());
        $params = SubmissionsQuery::params($request->query());
        $rows = SubmissionsQuery::query($params['type'], $params['view'], $params['q'])->orderByDesc('createdAt')->take(10_000)->get();
        $cols = self::EXPORT_COLUMNS[$params['type']];
        $csv = Csv::build($cols, $rows->map(fn ($r) => array_map(function (string $c) use ($r) {
            $v = $r->getAttribute($c);

            return $v instanceof \BackedEnum ? $v->value : $v;
        }, $cols)));
        Audit::log(AuditAction::SUBMISSIONS_EXPORTED, $user->id, $params['type'], null, ['count' => $rows->count(), 'view' => $params['view'], 'filtered' => $params['q'] !== null]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Csv::filename("sakila-{$params['type']}").'"',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    // ── actions (ActionResult JSON) ─────────────────────────────────────────────────────

    public function setStatus(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = SubmissionsQuery::authorize($request->user());
            $type = $request->input('type');
            $allowed = $type === 'contact' ? self::CONTACT_STATUSES : self::APPLICATION_STATUSES;
            $data = Validator::make($request->all(), [
                'type' => ['required', 'in:contact,volunteer,membership,partner'],
                'id' => ['required', 'string', 'regex:/^[a-z0-9]{10,40}$/i'],
                'status' => ['required', 'in:'.implode(',', $allowed)],
            ])->validate();
            $row = (SubmissionsQuery::MODELS[$data['type']])::query()->find($data['id']);
            if ($row === null) {
                return ActionResult::fail('NOT_FOUND', 'This submission no longer exists.');
            }
            $row->update(['status' => $data['status'], 'handledById' => $user->id]);
            Audit::log(AuditAction::SUBMISSION_UPDATED, $user->id, $data['type'], $data['id'], ['status' => $data['status']]);

            return ActionResult::ok(null, 'Status updated');
        });
    }

    /** Mark read/unread. Opening a NEW item marks it read automatically (no audit entry for that). */
    public function markRead(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = SubmissionsQuery::authorize($request->user());
            $data = Validator::make($request->all(), [
                'type' => ['required', 'in:contact,volunteer,membership,partner'],
                'id' => ['required', 'string', 'regex:/^[a-z0-9]{10,40}$/i'],
                'read' => ['required', 'boolean'],
            ])->validate();
            $model = SubmissionsQuery::MODELS[$data['type']];
            if ($data['read']) {
                $model::query()->where('id', $data['id'])->where('status', 'NEW')
                    ->update(['status' => $data['type'] === 'contact' ? 'READ' : 'IN_REVIEW']);
            } elseif ($model::query()->where('id', $data['id'])->update(['status' => 'NEW']) > 0) {
                Audit::log(AuditAction::SUBMISSION_UPDATED, $user->id, $data['type'], $data['id'], ['status' => 'NEW']);
            }

            return ActionResult::ok();
        });
    }

    public function saveNote(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = SubmissionsQuery::authorize($request->user());
            $data = Validator::make($request->all(), [
                'type' => ['required', 'in:contact,volunteer,membership,partner'],
                'id' => ['required', 'string', 'regex:/^[a-z0-9]{10,40}$/i'],
                'note' => ['nullable', 'string', 'max:5000'],
            ])->validate();
            $row = (SubmissionsQuery::MODELS[$data['type']])::query()->find($data['id']);
            if ($row === null) {
                return ActionResult::fail('NOT_FOUND', 'This submission no longer exists.');
            }
            $note = trim((string) ($data['note'] ?? ''));
            $row->update(['internalNote' => $note !== '' ? $note : null, 'handledById' => $user->id]);
            Audit::log(AuditAction::SUBMISSION_UPDATED, $user->id, $data['type'], $data['id'], ['field' => 'internalNote']);

            return ActionResult::ok(null, 'Note saved');
        });
    }

    /** Permanent delete (GDPR right to erasure). The audit entry keeps no personal data. */
    public function delete(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = SubmissionsQuery::authorize($request->user());
            $data = Validator::make($request->all(), [
                'type' => ['required', 'in:contact,volunteer,membership,partner,newsletter'],
                'id' => ['required', 'string', 'regex:/^[a-z0-9]{10,40}$/i'],
            ])->validate();
            $model = $data['type'] === 'newsletter' ? NewsletterSubscriber::class : SubmissionsQuery::MODELS[$data['type']];
            if ($model::query()->where('id', $data['id'])->delete() === 0) {
                return ActionResult::fail('NOT_FOUND', 'This item was already deleted.');
            }
            Audit::log(AuditAction::SUBMISSION_DELETED, $user->id, $data['type'], $data['id'], ['reason' => 'gdpr-erasure']);

            return ActionResult::ok(null, 'Deleted permanently');
        });
    }
}
