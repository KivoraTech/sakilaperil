<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use Illuminate\Support\Facades\Http;

/** Khalti KPG-2 (port of lib/payments/khalti.ts): initiate → redirect → lookup(pidx). Amounts in paisa. */
final class Khalti
{
    private static function base(): string
    {
        return PaymentConfig::khalti(PaymentConfig::resolve()['KHALTI']['testMode']);
    }

    private static function authHeader(): string
    {
        $k = trim((string) config('engagement.payments.khalti.secret_key'));
        if ($k === '') {
            throw new ProviderError('khalti', 'not configured');
        }

        return "Key {$k}";
    }

    /**
     * @param  array{donation: array<string, mixed>, frequency: string, description: string, siteUrl: string}  $input
     * @return array{kind: 'redirect', url: string, providerReference: string}
     */
    public static function initiate(array $input): array
    {
        $d = $input['donation'];
        $body = [
            'return_url' => $input['siteUrl'].'/api/donations/khalti/return',
            'website_url' => $input['siteUrl'],
            'amount' => $d['amount'],
            'purchase_order_id' => $d['reference'],
            'purchase_order_name' => mb_substr($input['description'], 0, 100),
        ];
        if (! empty($d['donorName']) || ! empty($d['donorEmail'])) {
            $body['customer_info'] = array_filter(['name' => $d['donorName'] ?? null, 'email' => $d['donorEmail'] ?? null]);
        }
        $res = Http::withHeaders(['Authorization' => self::authHeader()])->timeout(15)->post(self::base().'/epayment/initiate/', $body);
        $json = $res->json();
        if (! $res->successful() || ! is_string($json['pidx'] ?? null) || ! is_string($json['payment_url'] ?? null)) {
            throw new ProviderError('khalti', 'initiate failed ('.$res->status().')');
        }

        return ['kind' => 'redirect', 'url' => $json['payment_url'], 'providerReference' => $json['pidx']];
    }

    /** @return array<string, mixed> Khalti answers 400 for Expired / User canceled but still includes a status. */
    public static function lookup(string $pidx): array
    {
        if (preg_match('/^[A-Za-z0-9]{8,64}$/', $pidx) !== 1) {
            throw new ProviderError('khalti', 'bad pidx');
        }
        $res = Http::withHeaders(['Authorization' => self::authHeader()])->timeout(15)->post(self::base().'/epayment/lookup/', ['pidx' => $pidx]);
        $json = $res->json();
        $json = is_array($json) ? $json : [];
        if (! $res->successful() && ! is_string($json['status'] ?? null)) {
            throw new ProviderError('khalti', 'lookup failed ('.$res->status().')');
        }

        return $json;
    }
}
