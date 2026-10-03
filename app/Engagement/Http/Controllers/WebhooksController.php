<?php

declare(strict_types=1);

namespace App\Engagement\Http\Controllers;

use App\Engagement\Payments\Donations;
use App\Engagement\Payments\Interpret;
use App\Engagement\Payments\PaymentConfig;
use App\Engagement\Payments\PayPal;
use App\Engagement\Payments\Signatures;
use App\Engagement\Payments\Webhooks;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Payment webhooks (port of app/api/webhooks/{stripe,paypal}/route.ts). */
final class WebhooksController
{
    private const MAX_BODY = 512 * 1024;

    /**
     * POST /api/webhooks/stripe — events: checkout.session.completed / async_payment_succeeded /
     * async_payment_failed / expired, invoice.paid, charge.refunded.
     */
    public function stripe(Request $request): JsonResponse
    {
        $secret = trim((string) config('engagement.payments.stripe.webhook_secret'));
        if ($secret === '') {
            return response()->json(['error' => 'not configured'], 503);
        }
        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_BODY) {
            return response()->json(['error' => 'too large'], 413);
        }
        if (! Signatures::verifyStripe($raw, $request->headers->get('stripe-signature'), $secret)) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $event = json_decode($raw, true);
        $obj = is_array($event) ? ($event['data']['object'] ?? null) : null;
        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($obj)) {
            return response()->json(['error' => is_array($event) ? 'bad event' : 'bad json'], 400);
        }
        $type = $event['type'];

        try {
            $result = Webhooks::processOnce('STRIPE', $event['id'], $type, function () use ($type, $obj, $event): void {
                switch ($type) {
                    case 'checkout.session.completed':
                    case 'checkout.session.async_payment_succeeded':
                    case 'checkout.session.async_payment_failed':
                    case 'checkout.session.expired':
                        $donationId = is_string($obj['client_reference_id'] ?? null) ? $obj['client_reference_id'] : null;
                        $donation = $donationId !== null
                            ? Donation::query()->where('id', $donationId)->where('provider', 'STRIPE')->first()
                            : Donations::findByProviderRef('STRIPE', (string) ($obj['id'] ?? ''));
                        if ($donation === null) {
                            return;
                        }
                        $verified = Interpret::stripeSession($obj);
                        if ($type === 'checkout.session.async_payment_failed') {
                            $verified['outcome'] = 'FAILED';
                        }
                        Donations::applyVerified($donation->id, $verified);

                        return;
                    case 'invoice.paid':
                        // The first invoice is covered by checkout.session.completed; record renewals only.
                        if (($obj['billing_reason'] ?? null) !== 'subscription_cycle') {
                            return;
                        }
                        $subscriptionId = Interpret::stripeInvoiceSubscriptionId($obj);
                        if ($subscriptionId === null || ! is_string($obj['id'] ?? null)) {
                            return;
                        }
                        Donations::recordStripeRenewal(
                            $subscriptionId,
                            $obj['id'],
                            (int) ($obj['amount_paid'] ?? 0),
                            (string) ($obj['currency'] ?? 'eur'),
                            ($event['livemode'] ?? null) === false || PaymentConfig::resolve()['STRIPE']['testMode'],
                        );

                        return;
                    case 'charge.refunded':
                        if (($obj['refunded'] ?? null) !== true || ! is_string($obj['payment_intent'] ?? null)) {
                            return;
                        }
                        $donation = Donation::query()->where('provider', 'STRIPE')
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(`providerData`, '$.paymentIntentId')) = ?", [$obj['payment_intent']])
                            ->first();
                        if ($donation !== null) {
                            Donations::applyVerified($donation->id, ['outcome' => 'REFUNDED', 'data' => ['refundedCharge' => (string) ($obj['id'] ?? '')]]);
                        }

                        return;
                    default:
                        return; // acknowledged, ignored
                }
            });

            return response()->json(['received' => true, 'result' => $result]);
        } catch (Throwable $e) {
            Log::error('[webhooks] stripe processing failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'processing failed'], 500);
        }
    }

    /**
     * POST /api/webhooks/paypal — CHECKOUT.ORDER.APPROVED (capture if the donor closed the tab),
     * PAYMENT.CAPTURE.COMPLETED / DENIED / REFUNDED. Verified with PayPal's API, then re-read.
     */
    public function paypal(Request $request): JsonResponse
    {
        if (trim((string) config('engagement.payments.paypal.webhook_id')) === '') {
            return response()->json(['error' => 'not configured'], 503);
        }
        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_BODY) {
            return response()->json(['error' => 'too large'], 413);
        }
        $event = json_decode($raw, true);
        if (! is_array($event)) {
            return response()->json(['error' => 'bad json'], 400);
        }
        if (! is_string($event['id'] ?? null) || ! is_string($event['event_type'] ?? null) || ! is_array($event['resource'] ?? null)) {
            return response()->json(['error' => 'bad event'], 400);
        }
        $valid = false;
        try {
            $valid = PayPal::verifyWebhook($request, json_decode($raw));
        } catch (Throwable $e) {
            Log::error('[webhooks] paypal verification error', ['error' => $e->getMessage()]);
        }
        if (! $valid) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        $type = $event['event_type'];
        $resource = $event['resource'];
        $orderId = $type === 'CHECKOUT.ORDER.APPROVED'
            ? (is_string($resource['id'] ?? null) ? $resource['id'] : null)
            : (is_string($resource['supplementary_data']['related_ids']['order_id'] ?? null) ? $resource['supplementary_data']['related_ids']['order_id'] : null);

        try {
            $result = Webhooks::processOnce('PAYPAL', $event['id'], $type, function () use ($type, $orderId, $event): void {
                if ($orderId === null) {
                    return;
                }
                $donation = Donations::findByProviderRef('PAYPAL', $orderId);
                if ($donation === null) {
                    return;
                }
                switch ($type) {
                    case 'CHECKOUT.ORDER.APPROVED':
                        if ($donation->status->value === 'PENDING') {
                            Donations::applyVerified($donation->id, Interpret::paypalOrder(PayPal::captureOrder($orderId)));
                        }

                        return;
                    case 'PAYMENT.CAPTURE.COMPLETED':
                    case 'PAYMENT.CAPTURE.DENIED':
                        Donations::applyVerified($donation->id, Interpret::paypalOrder(PayPal::getOrder($orderId)));

                        return;
                    case 'PAYMENT.CAPTURE.REFUNDED':
                        Donations::applyVerified($donation->id, ['outcome' => 'REFUNDED', 'data' => ['refundEvent' => $event['id']]]);

                        return;
                    default:
                        return;
                }
            });

            return response()->json(['received' => true, 'result' => $result]);
        } catch (Throwable $e) {
            Log::error('[webhooks] paypal processing failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'processing failed'], 500);
        }
    }
}
