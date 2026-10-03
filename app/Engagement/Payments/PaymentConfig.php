<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

/**
 * Provider configuration (port of lib/payments/config.ts; ADR-008). Pure `resolve()` for tests.
 * Test mode: Stripe sk_test_/rk_test_ keys, PAYPAL_ENV/ESEWA_ENV/KHALTI_ENV != "live".
 */
final class PaymentConfig
{
    /**
     * @param  array<string, mixed>|null  $c  config('engagement.payments') shape
     * @return array{STRIPE: array{configured: bool, testMode: bool}, PAYPAL: array{configured: bool, testMode: bool}, ESEWA: array{configured: bool, testMode: bool}, KHALTI: array{configured: bool, testMode: bool}, stripeWebhook: bool, paypalWebhook: bool}
     */
    public static function resolve(?array $c = null): array
    {
        $c ??= (array) config('engagement.payments');
        $v = static fn (mixed $x): string => is_string($x) ? trim($x) : '';
        $stripeKey = $v($c['stripe']['secret_key'] ?? null);

        return [
            'STRIPE' => [
                'configured' => $stripeKey !== '' && preg_match('/^(sk|rk)_(test|live)_/', $stripeKey) === 1,
                'testMode' => $stripeKey === '' || preg_match('/^(sk|rk)_test_/', $stripeKey) === 1,
            ],
            'PAYPAL' => [
                'configured' => $v($c['paypal']['client_id'] ?? null) !== '' && $v($c['paypal']['client_secret'] ?? null) !== '',
                'testMode' => $v($c['paypal']['env'] ?? null) !== 'live',
            ],
            'ESEWA' => [
                'configured' => $v($c['esewa']['merchant_code'] ?? null) !== '' && $v($c['esewa']['secret_key'] ?? null) !== '',
                'testMode' => $v($c['esewa']['env'] ?? null) !== 'live',
            ],
            'KHALTI' => [
                'configured' => $v($c['khalti']['secret_key'] ?? null) !== '',
                'testMode' => $v($c['khalti']['env'] ?? null) !== 'live',
            ],
            'stripeWebhook' => $v($c['stripe']['webhook_secret'] ?? null) !== '',
            'paypalWebhook' => $v($c['paypal']['webhook_id'] ?? null) !== '',
        ];
    }

    /**
     * What the public DonateWidget needs to know (no secrets).
     *
     * @return array<string, array{available: bool, testMode: bool}>
     */
    public static function publicStatus(): array
    {
        $c = self::resolve();
        $out = [];
        foreach (DonationSchema::ONLINE_PROVIDERS as $p) {
            $out[$p] = ['available' => $c[$p]['configured'], 'testMode' => $c[$p]['configured'] && $c[$p]['testMode']];
        }

        return $out;
    }

    public static function paypalBase(bool $test): string
    {
        return $test ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    public static function esewaForm(bool $test): string
    {
        return $test ? 'https://rc-epay.esewa.com.np/api/epay/main/v2/form' : 'https://epay.esewa.com.np/api/epay/main/v2/form';
    }

    public static function esewaStatus(bool $test): string
    {
        return $test ? 'https://rc.esewa.com.np/api/epay/transaction/status/' : 'https://esewa.com.np/api/epay/transaction/status/';
    }

    public static function khalti(bool $test): string
    {
        return $test ? 'https://dev.khalti.com/api/v2' : 'https://khalti.com/api/v2';
    }

    public const STRIPE = 'https://api.stripe.com/v1';
}
