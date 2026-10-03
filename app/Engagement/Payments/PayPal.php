<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** PayPal Orders v2: create → buyer approves → we CAPTURE server-side (port of lib/payments/paypal.ts). */
final class PayPal
{
    private static function base(): string
    {
        return PaymentConfig::paypalBase(PaymentConfig::resolve()['PAYPAL']['testMode']);
    }

    private static function accessToken(): string
    {
        $id = trim((string) config('engagement.payments.paypal.client_id'));
        $secret = trim((string) config('engagement.payments.paypal.client_secret'));
        if ($id === '' || $secret === '') {
            throw new ProviderError('paypal', 'not configured');
        }
        $res = Http::withBasicAuth($id, $secret)->asForm()->timeout(15)
            ->post(self::base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        $token = $res->json('access_token');
        if (! $res->successful() || ! is_string($token)) {
            throw new ProviderError('paypal', 'auth failed ('.$res->status().')');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, ok: bool, json: array<string, mixed>}
     */
    private static function request(string $method, string $path, ?array $body = null, ?string $requestId = null): array
    {
        $req = Http::withToken(self::accessToken())->timeout(20)->acceptJson()
            ->withHeaders(array_filter(['PayPal-Request-Id' => $requestId]));
        $res = $method === 'GET'
            ? $req->get(self::base().$path)
            : $req->withBody($body === [] ? '{}' : (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'application/json')->post(self::base().$path);
        $json = $res->json();

        return ['status' => $res->status(), 'ok' => $res->successful(), 'json' => is_array($json) ? $json : []];
    }

    /**
     * @param  array{donation: array<string, mixed>, frequency: string, description: string, siteUrl: string}  $input
     * @return array{kind: 'redirect', url: string, providerReference: string}
     */
    public static function createOrder(array $input): array
    {
        $d = $input['donation'];
        $res = self::request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $d['reference'],
                'custom_id' => $d['id'],
                'description' => mb_substr($input['description'], 0, 127),
                'amount' => ['currency_code' => $d['currency'], 'value' => number_format($d['amount'] / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'brand_name' => 'Sakila per il Nepal',
                'locale' => $d['locale'] === 'it' ? 'it-IT' : 'en-GB',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $input['siteUrl'].'/api/donations/paypal/return',
                'cancel_url' => $input['siteUrl'].'/'.$d['locale'].'/donate/cancelled?ref='.$d['reference'],
            ]]],
        ], 'order-'.$d['id']);
        if (! $res['ok']) {
            throw new ProviderError('paypal', 'create order failed ('.$res['status'].')');
        }
        $approve = null;
        foreach ((array) ($res['json']['links'] ?? []) as $link) {
            if (is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true)) {
                $approve = $link;
                break;
            }
        }
        if ($approve === null || ! is_string($res['json']['id'] ?? null)) {
            throw new ProviderError('paypal', 'no approval link');
        }

        return ['kind' => 'redirect', 'url' => (string) $approve['href'], 'providerReference' => $res['json']['id']];
    }

    /** @return array<string, mixed> Capture an approved order; already captured → current order state. */
    public static function captureOrder(string $orderId): array
    {
        self::assertOrderId($orderId);
        $res = self::request('POST', "/v2/checkout/orders/{$orderId}/capture", [], "capture-{$orderId}");
        if ($res['ok']) {
            return $res['json'];
        }
        $current = self::request('GET', "/v2/checkout/orders/{$orderId}");
        if ($current['ok']) {
            return $current['json'];
        }
        throw new ProviderError('paypal', 'capture failed ('.$res['status'].')');
    }

    /** @return array<string, mixed> */
    public static function getOrder(string $orderId): array
    {
        self::assertOrderId($orderId);
        $res = self::request('GET', "/v2/checkout/orders/{$orderId}");
        if (! $res['ok']) {
            throw new ProviderError('paypal', 'get order failed ('.$res['status'].')');
        }

        return $res['json'];
    }

    /** Verify a webhook with PayPal's verify-webhook-signature API (requires PAYPAL_WEBHOOK_ID). */
    public static function verifyWebhook(Request $request, mixed $event): bool
    {
        $webhookId = trim((string) config('engagement.payments.paypal.webhook_id'));
        if ($webhookId === '') {
            return false;
        }
        $h = static fn (string $n): string => (string) $request->headers->get($n, '');
        $res = self::request('POST', '/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $h('paypal-auth-algo'),
            'cert_url' => $h('paypal-cert-url'),
            'transmission_id' => $h('paypal-transmission-id'),
            'transmission_sig' => $h('paypal-transmission-sig'),
            'transmission_time' => $h('paypal-transmission-time'),
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ]);

        return $res['ok'] && ($res['json']['verification_status'] ?? null) === 'SUCCESS';
    }

    private static function assertOrderId(string $orderId): void
    {
        if (preg_match('/^[A-Z0-9]{8,32}$/', $orderId) !== 1) {
            throw new ProviderError('paypal', 'bad order id');
        }
    }
}
