<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\Permissions;
use App\Engagement\Forms\Csv;
use App\Engagement\Payments\Donations;
use App\Engagement\Payments\PaymentConfig;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Project;
use App\Queries\Admin\DonationsQuery;
use App\Queries\Admin\SubmissionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Donations admin (ENGAGEMENT; port of app/admin/(shell)/donations/**). Online donations are
 * read-only (only verified provider data changes them); staff record offline bank transfers.
 */
final class DonationsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = DonationsQuery::authorize($request->user());
        $params = DonationsQuery::params($request->query());
        $canManage = Permissions::can($user->role, 'donations.manage');
        $config = PaymentConfig::resolve();

        return Inertia::render('admin/donations/index', [
            'params' => $params,
            'data' => DonationsQuery::list($params),
            'projects' => $canManage ? DonationsQuery::projectOptions() : [],
            'canManage' => $canManage,
            'providers' => array_map(fn ($p) => $config[$p], array_combine(['STRIPE', 'PAYPAL', 'ESEWA', 'KHALTI'], ['STRIPE', 'PAYPAL', 'ESEWA', 'KHALTI'])),
        ]);
    }

    public function show(Request $request, string $id): Response|HttpResponse
    {
        $user = DonationsQuery::authorize($request->user());
        $d = preg_match('/^[a-z0-9]{10,40}$/i', $id) === 1
            ? Donation::query()->with(['project.translations', 'campaign.translations'])->find($id)
            : null;
        if ($d === null) {
            return AdminNotFound::response($request, 'donation', '/admin/donations', 'Back to donations');
        }
        $titles = fn ($rel) => $rel ? $rel->translations->map(fn ($t) => ['locale' => $t->locale->value, 'title' => $t->title])->values()->all() : null;

        return Inertia::render('admin/donations/show', [
            'donation' => [
                'id' => $d->id,
                'reference' => $d->reference,
                'provider' => $d->provider->value,
                'status' => $d->status->value,
                'amount' => $d->amount,
                'currency' => $d->currency,
                'donorName' => $d->donorName,
                'donorEmail' => $d->donorEmail,
                'isAnonymous' => $d->isAnonymous,
                'isTestMode' => $d->isTestMode,
                'message' => $d->message,
                'locale' => $d->locale->value,
                'providerReference' => $d->providerReference,
                'providerData' => is_array($d->providerData) ? $d->providerData : (object) [],
                'createdAt' => SubmissionsQuery::iso($d->createdAt),
                'completedAt' => $d->completedAt ? SubmissionsQuery::iso($d->completedAt) : null,
                'project' => $titles($d->project),
                'campaign' => $titles($d->campaign),
            ],
            'canManage' => Permissions::can($user->role, 'donations.manage'),
        ]);
    }

    /** GET /admin/donations/export?<filters> — CSV (ADMIN+), audited. */
    public function export(Request $request): HttpResponse
    {
        $user = DonationsQuery::authorize($request->user());
        $params = DonationsQuery::params($request->query());
        $rows = DonationsQuery::query($params)->orderByDesc('createdAt')->take(20_000)
            ->with(['project.translations' => fn ($t) => $t->where('locale', 'it')])->get();
        $csv = Csv::build(
            ['reference', 'createdAt', 'completedAt', 'status', 'provider', 'amount', 'currency', 'frequency', 'testMode', 'donorName', 'donorEmail', 'anonymous', 'project', 'providerReference', 'locale'],
            $rows->map(fn (Donation $d) => [
                $d->reference, $d->createdAt, $d->completedAt, $d->status->value, $d->provider->value,
                number_format($d->amount / 100, 2, '.', ''), $d->currency,
                (is_array($d->providerData) ? ($d->providerData['frequency'] ?? null) : null) ?? 'once',
                $d->isTestMode, $d->donorName, $d->donorEmail, $d->isAnonymous,
                $d->project?->translations->first()->title ?? '', $d->providerReference, $d->locale->value,
            ]),
        );
        Audit::log(AuditAction::DONATIONS_EXPORTED, $user->id, 'donation', null, ['count' => $rows->count(), 'provider' => $params['provider'] ?? null, 'status' => $params['status'] ?? null]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Csv::filename('sakila-donations').'"',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function recordBankTransfer(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = DonationsQuery::authorize($request->user(), 'donations.manage');
            $data = Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'gt:0', 'max:1000000'],
                'currency' => ['required', 'in:EUR,NPR'],
                'receivedOn' => ['required', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
                'donorName' => ['nullable', 'string', 'max:120'],
                'donorEmail' => ['nullable', 'email', 'max:254'],
                'projectId' => ['nullable', 'string', 'max:64'],
                'note' => ['nullable', 'string', 'max:500'],
                'isAnonymous' => ['nullable', 'boolean'],
            ])->validate();
            $received = Carbon::parse($data['receivedOn'].' 12:00:00', 'UTC');
            if ($received->getTimestamp() > time() + 86_400) {
                return ActionResult::fail('VALIDATION', 'The date cannot be in the future.');
            }
            $project = ! empty($data['projectId']) ? Project::query()->whereKey($data['projectId'])->value('id') : null;
            $row = Donation::query()->create([
                'reference' => Donations::generateReference($received),
                'provider' => 'BANK_TRANSFER',
                'status' => 'SUCCEEDED',
                'amount' => (int) round((float) $data['amount'] * 100),
                'currency' => $data['currency'],
                'donorName' => ($data['donorName'] ?? null) ?: null,
                'donorEmail' => ! empty($data['donorEmail']) ? strtolower($data['donorEmail']) : null,
                'message' => ($data['note'] ?? null) ?: null,
                'isAnonymous' => (bool) ($data['isAnonymous'] ?? false),
                'locale' => 'it',
                'projectId' => $project,
                'providerData' => ['recordedById' => $user->id, 'manual' => true],
                'completedAt' => $received,
                'createdAt' => $received,
            ]);
            Audit::log(AuditAction::DONATION_UPDATED, $user->id, 'donation', $row->id, ['op' => 'record-bank-transfer', 'amount' => $row->amount, 'currency' => $row->currency]);

            return ActionResult::ok(['id' => $row->id], 'Bank transfer recorded');
        });
    }

    public function setManualStatus(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $user = DonationsQuery::authorize($request->user(), 'donations.manage');
            $data = Validator::make($request->all(), [
                'id' => ['required', 'string', 'regex:/^[a-z0-9]{10,40}$/i'],
                'status' => ['required', 'in:SUCCEEDED,CANCELED,REFUNDED'],
            ])->validate();
            if (Donation::query()->where('id', $data['id'])->where('provider', 'BANK_TRANSFER')->update(['status' => $data['status']]) === 0) {
                return ActionResult::fail('FORBIDDEN', 'Only manually recorded bank transfers can be changed here.');
            }
            Audit::log(AuditAction::DONATION_UPDATED, $user->id, 'donation', $data['id'], ['op' => 'status', 'status' => $data['status']]);

            return ActionResult::ok(null, 'Status updated');
        });
    }
}
