<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout via the REST API (port of lib/payments/stripe.ts): hosted Checkout, so no card
 * data touches our server. Monthly gifts use mode=subscription with an inline monthly price.
 */
final class Stripe
{
    private static function key(): string
    {
        $k = trim((string) config('engagement.payments.stripe.secret_key'));
        if ($k === '') {
            throw new ProviderError('stripe', 'not configured');
        }

        return $k;
    }

    /**
     * Stripe's form encoding for nested params: a[b][c]=v (keys in insertion order).
     *
     * @param  array<string|int, mixed>  $params
     * @return list<array{0: string, 1: string}>
     */
    public static function form(array $params, string $prefix = ''): array
    {
        $out = [];
        $walk = function (mixed $value, string $path) use (&$walk, &$out): void {
            if ($value === null) {
                return;
            }
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $path === '' ? (string) $k : "{$path}[{$k}]");
                }

                return;
            }
            $out[] = [$path, is_bool($value) ? ($value ? 'true' : 'false') : (string) $value];
        };
        $walk($params, $prefix);

        return $out;
    }

    /** @param  list<array{0: string, 1: string}>  $pairs */
    public static function encode(array $pairs): string
    {
        return implode('&', array_map(static fn ($p) => rawurlencode($p[0]).'='.rawurlencode($p[1]), $pairs));
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, ?array $params = null, ?string $idempotencyKey = null): array
    {
        $req = Http::withToken(self::key())->timeout(15)->withHeaders(array_filter(['Idempotency-Key' => $idempotencyKey]));
        $res = $method === 'GET'
            ? $req->get(PaymentConfig::STRIPE.$path)
            : $req->withBody(self::encode(self::form($params ?? [])), 'application/x-www-form-urlencoded')->post(PaymentConfig::STRIPE.$path);
        $json = $res->json();
        $json = is_array($json) ? $json : [];
        if (! $res->successful()) {
            throw new ProviderError('stripe', is_string($json['error']['message'] ?? null) ? $json['error']['message'] : 'HTTP '.$res->status());
        }

        return $json;
    }

    /**
     * @param  array{donation: array<string, mixed>, frequency: string, description: string, siteUrl: string}  $input
     * @return array{kind: 'redirect', url: string, providerReference: string}
     */
    public static function createCheckout(array $input): array
    {
        $d = $input['donation'];
        $monthly = $input['frequency'] === 'monthly';
        $meta = ['donationId' => $d['id'], 'reference' => $d['reference']];
        $priceData = [
            'currency' => strtolower($d['currency']),
            'unit_amount' => $d['amount'],
            'product_data' => ['name' => $input['description']],
        ];
        if ($monthly) {
            $priceData['recurring'] = ['interval' => 'month'];
        }
        $params = [
            'mode' => $monthly ? 'subscription' : 'payment',
            'client_reference_id' => $d['id'],
            'locale' => $d['locale'],
            'customer_email' => $d['donorEmail'] ?? null,
            'success_url' => $input['siteUrl'].'/api/donations/stripe/return?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $input['siteUrl'].'/'.$d['locale'].'/donate/cancelled?ref='.$d['reference'],
            'metadata' => $meta,
            'line_items' => [['quantity' => 1, 'price_data' => $priceData]],
        ];
        if ($monthly) {
            $params['subscription_data'] = ['metadata' => $meta];
        } else {
            $params['payment_intent_data'] = ['metadata' => $meta];
            $params['submit_type'] = 'donate';
        }
        $session = self::request('POST', '/checkout/sessions', $params, 'checkout-'.$d['id']);
        if (! is_string($session['url'] ?? null) || ! is_string($session['id'] ?? null)) {
            throw new ProviderError('stripe', 'no checkout url');
        }

        return ['kind' => 'redirect', 'url' => $session['url'], 'providerReference' => $session['id']];
    }

    /** @return array<string, mixed> */
    public static function retrieveSession(string $sessionId): array
    {
        if (preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sessionId) !== 1) {
            throw new ProviderError('stripe', 'bad session id');
        }

        return self::request('GET', "/checkout/sessions/{$sessionId}");
    }
}
