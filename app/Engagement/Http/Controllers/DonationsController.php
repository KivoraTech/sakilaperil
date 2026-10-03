<?php

declare(strict_types=1);

namespace App\Engagement\Http\Controllers;

use App\Engagement\Forms\FormSchemas;
use App\Engagement\Http\PublicJson;
use App\Engagement\Newsletter\Newsletter;
use App\Engagement\Payments\DonationError;
use App\Engagement\Payments\Donations;
use App\Engagement\Payments\DonationSchema;
use App\Engagement\Payments\Esewa;
use App\Engagement\Payments\Interpret;
use App\Engagement\Payments\Khalti;
use App\Engagement\Payments\PayPal;
use App\Engagement\Payments\Signatures;
use App\Engagement\Payments\Stripe;
use App\Engagement\Spam\Guard;
use App\Engagement\Spam\RateLimiter;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** /api/donations + provider return URLs (port of app/api/donations/**). */
final class DonationsController
{
    /**
     * POST /api/donations — create a PENDING donation and return how to continue:
     * { kind: "redirect", url } (Stripe, PayPal, Khalti) or { kind: "form", action, fields } (eSewa).
     */
    public function start(Request $request): JsonResponse
    {
        if (! PublicJson::isSameOrigin($request)) {
            return PublicJson::error(403, 'FORBIDDEN');
        }
        $record = PublicJson::readJson($request);
        if ($record === null) {
            return PublicJson::error(400, 'VALIDATION');
        }
        $meta = FormSchemas::spamMeta($record);
        if (! $meta->ok()) {
            return PublicJson::error(400, 'SPAM');
        }
        $guard = Guard::check('donation', $meta->data);
        if (! $guard['ok']) {
            return PublicJson::error($guard['status'], $guard['code'], ['retryAfterSec' => $guard['retryAfterSec'] ?? 0]);
        }
        $parsed = DonationSchema::parse($record);
        if (! $parsed->ok()) {
            RateLimiter::refund('donation', $guard['ipHash']);

            return PublicJson::error(422, 'VALIDATION', ['fieldErrors' => $parsed->fieldErrors]);
        }
        $locale = PublicJson::localeOf($record['locale'] ?? null);
        try {
            $start = Donations::start($parsed->data, $locale);
            if (($parsed->data['newsletter'] ?? false) && isset($parsed->data['donorEmail'])) {
                try {
                    Newsletter::subscribe($parsed->data['donorEmail'], $locale, 'donation');
                } catch (Throwable $e) {
                    Log::error('[donations] newsletter opt-in failed', ['error' => $e->getMessage()]);
                }
            }

            return $start['kind'] === 'redirect'
                ? PublicJson::ok(['kind' => 'redirect', 'url' => $start['url'], 'reference' => $start['reference']], 201)
                : PublicJson::ok(['kind' => 'form', 'action' => $start['action'], 'fields' => $start['fields'], 'reference' => $start['reference']], 201);
        } catch (DonationError $e) {
            return $e->codeName === 'NOT_CONFIGURED' ? PublicJson::error(409, 'NOT_CONFIGURED') : PublicJson::error(502, 'PROVIDER_ERROR');
        } catch (Throwable $e) {
            Log::error('[donations] unexpected error', ['error' => $e->getMessage()]);

            return PublicJson::error(500, 'ERROR');
        }
    }

    /** GET /api/donations/stripe/return?session_id=cs_… — the id is only a lookup key; Stripe's API decides. */
    public function stripeReturn(Request $request): RedirectResponse
    {
        $sessionId = (string) ($request->query('session_id') ?? '');
        $donation = $sessionId !== '' ? Donations::findByProviderRef('STRIPE', $sessionId) : null;
        if ($donation === null) {
            return Donations::resultRedirect(null);
        }

        return $this->verifyAndRedirect($donation, fn () => Interpret::stripeSession(Stripe::retrieveSession($sessionId)), 'stripe return verification');
    }

    /** GET /api/donations/paypal/return?token=<orderId> — we capture server-side; only PayPal's response decides. */
    public function paypalReturn(Request $request): RedirectResponse
    {
        $orderId = (string) ($request->query('token') ?? '');
        $donation = $orderId !== '' ? Donations::findByProviderRef('PAYPAL', $orderId) : null;
        if ($donation === null) {
            return Donations::resultRedirect(null);
        }
        if ($donation->status->value !== 'PENDING') {
            return Donations::resultRedirect($donation);
        }

        return $this->verifyAndRedirect($donation, fn () => Interpret::paypalOrder(PayPal::captureOrder($orderId)), 'paypal capture');
    }

    /** GET /api/donations/khalti/return?pidx=… — the `status` query param is ignored; lookup decides. */
    public function khaltiReturn(Request $request): RedirectResponse
    {
        $pidx = (string) ($request->query('pidx') ?? '');
        $donation = $pidx !== '' ? Donations::findByProviderRef('KHALTI', $pidx) : null;
        if ($donation === null) {
            return Donations::resultRedirect(null);
        }

        return $this->verifyAndRedirect($donation, fn () => Interpret::khaltiLookup(Khalti::lookup($pidx)), 'khalti lookup');
    }

    /** GET /api/donations/esewa/success?data=<base64> — verify the callback HMAC, then the status API. */
    public function esewaSuccess(Request $request): RedirectResponse
    {
        try {
            $secret = Esewa::secret();
        } catch (Throwable) {
            return Donations::resultRedirect(null);
        }
        $callback = Signatures::decodeEsewaCallback($request->query('data'), $secret);
        if ($callback === null) {
            return Donations::resultRedirect(null);
        }
        $donation = Donations::findByProviderRef('ESEWA', (string) $callback['transaction_uuid']);
        if ($donation === null) {
            return Donations::resultRedirect(null);
        }

        return $this->verifyAndRedirect($donation, function () use ($donation, $callback) {
            $verified = Interpret::esewaStatus(Esewa::status($donation->reference, $donation->amount));
            $verified['data']['transactionCode'] = is_scalar($callback['transaction_code'] ?? null) ? (string) $callback['transaction_code'] : null;

            return $verified;
        }, 'esewa status check');
    }

    /** GET /api/donations/esewa/failure/{reference} — the status API decides (abandoned → CANCELED). */
    public function esewaFailure(string $ref): RedirectResponse
    {
        $donation = Donations::isReference($ref)
            ? Donation::query()->where('provider', 'ESEWA')->where('reference', $ref)->first()
            : null;
        if ($donation === null) {
            return Donations::resultRedirect(null);
        }
        try {
            $result = Donations::applyVerified($donation->id, Interpret::esewaStatus(Esewa::status($donation->reference, $donation->amount)));

            return Donations::resultRedirect($donation, $result['status'] === 'PENDING' ? 'CANCELED' : $result['status']);
        } catch (Throwable $e) {
            Log::error('[donations] esewa failure check failed', ['error' => $e->getMessage()]);

            return Donations::resultRedirect($donation, 'CANCELED');
        }
    }

    /** @param  callable(): array<string, mixed>  $verify */
    private function verifyAndRedirect(Donation $donation, callable $verify, string $what): RedirectResponse
    {
        try {
            $result = Donations::applyVerified($donation->id, $verify());

            return Donations::resultRedirect($donation, $result['status']);
        } catch (Throwable $e) {
            Log::error("[donations] {$what} failed", ['error' => $e->getMessage()]);

            return Donations::resultRedirect($donation); // still PENDING → "we are confirming your gift"
        }
    }
}
