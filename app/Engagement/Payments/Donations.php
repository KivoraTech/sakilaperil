<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use App\Engagement\Mail\Notify;
use App\Enums\ContentStatus;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Donation lifecycle (port of lib/payments/service.ts). */
final class Donations
{
    private const REF_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

    private const DESCRIPTIONS = [
        'it' => ['once' => 'Donazione a Sakila per il Nepal', 'monthly' => 'Donazione mensile a Sakila per il Nepal'],
        'en' => ['once' => 'Donation to Sakila per il Nepal', 'monthly' => 'Monthly donation to Sakila per il Nepal'],
    ];

    /** Human-friendly, unguessable reference, e.g. "SKL-2026-7KQ9M2XA". */
    public static function generateReference(?Carbon $now = null): string
    {
        $bytes = random_bytes(8);
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= self::REF_ALPHABET[ord($bytes[$i]) % strlen(self::REF_ALPHABET)];
        }

        return 'SKL-'.($now ?? Carbon::now('UTC'))->utc()->format('Y').'-'.$s;
    }

    public static function isReference(string $value): bool
    {
        return preg_match('/^SKL-\d{4}-[A-Z2-9]{8}$/', $value) === 1;
    }

    /**
     * Create a PENDING donation and start the provider checkout. The row only leaves PENDING through
     * applyVerified() after server-side verification (webhook / provider API).
     *
     * @param  array<string, mixed>  $data  DonationSchema::parse()->data
     * @return array<string, mixed> checkout start + reference
     */
    public static function start(array $data, string $locale): array
    {
        $config = PaymentConfig::resolve();
        $providerName = $data['provider'];
        if (! $config[$providerName]['configured']) {
            throw new DonationError('NOT_CONFIGURED');
        }
        // Only attach existing, published projects / non-archived campaigns (ids come from the browser).
        $projectId = isset($data['projectId']) && $data['projectId'] !== ''
            ? Project::query()->where('id', $data['projectId'])->where('status', ContentStatus::PUBLISHED->value)->value('id')
            : null;
        $campaignId = isset($data['campaignId']) && $data['campaignId'] !== ''
            ? Campaign::query()->where('id', $data['campaignId'])->where('status', '!=', ContentStatus::ARCHIVED->value)->value('id')
            : null;

        $currency = DonationSchema::CURRENCY_FOR[$providerName];
        $frequency = $data['frequency'] ?? 'once';
        $donation = Donation::query()->create([
            'reference' => self::generateReference(),
            'provider' => $providerName,
            'status' => 'PENDING',
            'amount' => DonationSchema::toMinor($data['amount']),
            'currency' => $currency,
            'donorName' => $data['donorName'] ?? null,
            'donorEmail' => $data['donorEmail'] ?? null,
            'message' => $data['message'] ?? null,
            'isAnonymous' => (bool) ($data['isAnonymous'] ?? false),
            'locale' => $locale,
            'projectId' => $projectId,
            'campaignId' => $campaignId,
            'isTestMode' => $config[$providerName]['testMode'],
            'providerData' => ['frequency' => $frequency],
        ]);

        $input = [
            'donation' => [
                'id' => $donation->id,
                'reference' => $donation->reference,
                'amount' => $donation->amount,
                'currency' => $currency,
                'locale' => $locale,
                'donorEmail' => $donation->donorEmail,
                'donorName' => $donation->donorName,
            ],
            'frequency' => $frequency,
            'description' => self::DESCRIPTIONS[$locale][$frequency] ?? self::DESCRIPTIONS['it']['once'],
            'siteUrl' => rtrim((string) config('sakila.site_url'), '/'),
        ];

        try {
            $start = match ($providerName) {
                'STRIPE' => Stripe::createCheckout($input),
                'PAYPAL' => PayPal::createOrder($input),
                'ESEWA' => Esewa::createForm($input),
                'KHALTI' => Khalti::initiate($input),
            };
            $donation->update(['providerReference' => $start['providerReference']]);

            return [...$start, 'reference' => $donation->reference];
        } catch (Throwable $e) {
            Log::error("[payments] {$providerName} checkout failed for {$donation->reference}", ['error' => $e->getMessage()]);
            $donation->update([
                'status' => 'FAILED',
                'providerData' => [
                    'frequency' => $frequency,
                    'error' => $e instanceof ProviderError ? mb_substr($e->getMessage(), 0, 200) : 'checkout failed',
                ],
            ]);
            throw new DonationError('PROVIDER_ERROR');
        }
    }

    /**
     * Apply a VERIFIED provider outcome. Idempotent: the conditional update only matches while the
     * row is still in a state allowing the transition, so repeated webhooks / a webhook racing the
     * browser return change nothing the second time. A SUCCEEDED outcome whose amount/currency differs
     * from what we asked for is stored as FAILED.
     *
     * @param  array<string, mixed>  $verified  Interpret::* result
     * @return array{changed: bool, status: string}
     */
    public static function applyVerified(string $donationId, array $verified): array
    {
        $current = Donation::query()->find($donationId);
        if ($current === null) {
            return ['changed' => false, 'status' => 'FAILED'];
        }
        $from = $current->status->value;
        $target = $verified['outcome'];
        $amountMinor = $verified['amountMinor'] ?? null;
        $currency = $verified['currency'] ?? null;
        $mismatch = $target === 'SUCCEEDED'
            && (($amountMinor !== null && $amountMinor !== $current->amount)
                || ($currency !== null && strtoupper($currency) !== $current->currency));
        if ($mismatch) {
            Log::error("[payments] amount/currency mismatch for {$current->reference}");
            $target = 'FAILED';
        }
        if (! Interpret::canTransition($from, $target)) {
            return ['changed' => false, 'status' => $from];
        }
        $previous = is_array($current->providerData) ? $current->providerData : [];
        $providerData = [...$previous, ...($verified['data'] ?? [])];
        if ($mismatch) {
            $providerData['mismatch'] = true;
            $providerData['reportedAmount'] = $amountMinor;
        }

        $changed = DB::transaction(function () use ($current, $donationId, $from, $target, $providerData, $verified): bool {
            $now = Carbon::now('UTC');
            $count = Donation::query()->where('id', $donationId)->where('status', $from)->update([
                'status' => $target,
                'providerData' => json_encode($providerData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'completedAt' => $target === 'SUCCEEDED' ? $now->format('Y-m-d H:i:s.v') : $current->completedAt?->utc()->format('Y-m-d H:i:s.v'),
                'donorEmail' => $current->donorEmail ?? ($verified['donorEmail'] ?? null),
                'donorName' => $current->donorName ?? ($verified['donorName'] ?? null),
                'updatedAt' => $now->format('Y-m-d H:i:s.v'),
            ]);
            if ($count !== 1) {
                return false;
            }
            if ($current->campaignId !== null && ($target === 'SUCCEEDED' || $from === 'SUCCEEDED')) {
                $delta = $target === 'SUCCEEDED' ? $current->amount : -$current->amount;
                Campaign::query()->where('id', $current->campaignId)->where('currency', $current->currency)->increment('raisedAmount', $delta);
            }

            return true;
        });

        if ($changed && $target === 'SUCCEEDED') {
            Notify::team('donation', $donationId);
        }

        return ['changed' => $changed, 'status' => $changed ? $target : $from];
    }

    /** Record a Stripe subscription renewal (invoice.paid, subscription_cycle) as a new donation. */
    public static function recordStripeRenewal(string $subscriptionId, string $invoiceId, int $amountMinor, string $currency, bool $testMode): bool
    {
        $original = Donation::query()
            ->where('provider', 'STRIPE')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`providerData`, '$.subscriptionId')) = ?", [$subscriptionId])
            ->orderBy('createdAt')
            ->first();
        if ($original === null) {
            return false;
        }
        try {
            $row = Donation::query()->create([
                'reference' => self::generateReference(),
                'provider' => 'STRIPE',
                'status' => 'SUCCEEDED',
                'amount' => $amountMinor,
                'currency' => strtoupper($currency),
                'donorName' => $original->donorName,
                'donorEmail' => $original->donorEmail,
                'isAnonymous' => $original->isAnonymous,
                'locale' => $original->locale,
                'projectId' => $original->projectId,
                'campaignId' => $original->campaignId,
                'providerReference' => $invoiceId,
                'providerData' => ['frequency' => 'monthly', 'subscriptionId' => $subscriptionId, 'invoiceId' => $invoiceId, 'renewalOf' => $original->reference],
                'isTestMode' => $testMode,
                'completedAt' => Carbon::now('UTC'),
            ]);
        } catch (QueryException $e) {
            // Unique (provider, providerReference) → already recorded (idempotent).
            if (($e->errorInfo[1] ?? null) === 1062) {
                return false;
            }
            throw $e;
        }
        if ($row->campaignId !== null) {
            Campaign::query()->where('id', $row->campaignId)->where('currency', $row->currency)->increment('raisedAmount', $row->amount);
        }
        Notify::team('donation', $row->id);

        return true;
    }

    public static function findByProviderRef(string $provider, string $ref): ?Donation
    {
        return Donation::query()->where('provider', $provider)->where('providerReference', $ref)->first();
    }

    /**
     * Public, minimal view of a donation for the thank-you page (no email, no provider data).
     *
     * @return array{reference: string, status: string, amount: int, currency: string, provider: string, firstName: ?string, isTestMode: bool, frequency: string, project: list<array{locale: string, title: string, slug: string}>}|null
     */
    public static function summary(string $reference): ?array
    {
        if (! self::isReference($reference)) {
            return null;
        }
        $d = Donation::query()->with('project.translations')->where('reference', $reference)->first();
        if ($d === null) {
            return null;
        }
        $frequency = (is_array($d->providerData) ? ($d->providerData['frequency'] ?? null) : null) === 'monthly' ? 'monthly' : 'once';
        $firstName = null;
        if (! $d->isAnonymous && $d->donorName !== null) {
            $firstName = preg_split('/\s+/', $d->donorName)[0] ?? null;
        }
        $project = [];
        foreach ($d->projectId !== null && $d->project !== null ? $d->project->translations : [] as $t) {
            $project[] = ['locale' => $t->locale->value, 'title' => $t->title, 'slug' => $t->slug];
        }

        return [
            'reference' => $d->reference,
            'status' => $d->status->value,
            'amount' => $d->amount,
            'currency' => $d->currency,
            'provider' => $d->provider->value,
            'firstName' => $firstName,
            'isTestMode' => $d->isTestMode,
            'frequency' => $frequency,
            'project' => $project,
        ];
    }

    /** Localized result page after server-side verification (port of lib/payments/redirects.ts). */
    public static function resultRedirect(?Donation $donation, ?string $status = null, string $fallbackLocale = 'it'): RedirectResponse
    {
        $base = rtrim((string) config('sakila.site_url'), '/');
        if ($donation === null) {
            return redirect()->away("{$base}/{$fallbackLocale}/donate/cancelled", 303);
        }
        $status ??= $donation->status->value;
        $page = $status === 'SUCCEEDED' || $status === 'PENDING' ? 'thank-you' : 'cancelled';

        return redirect()->away("{$base}/{$donation->locale->value}/donate/{$page}?ref={$donation->reference}", 303);
    }
}
