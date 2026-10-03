<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use Illuminate\Support\Facades\Http;

/**
 * eSewa ePay v2 (port of lib/payments/esewa.ts): the browser POSTs an HMAC-signed form to eSewa,
 * which redirects back with ?data=<base64 JSON>; we verify the signature AND call the status API.
 * transaction_uuid = our donation reference.
 */
final class Esewa
{
    public const SIGNED_FIELDS = 'total_amount,transaction_uuid,product_code';

    /** @return array{code: string, secret: string} */
    private static function creds(): array
    {
        $code = trim((string) config('engagement.payments.esewa.merchant_code'));
        $secret = trim((string) config('engagement.payments.esewa.secret_key'));
        if ($code === '' || $secret === '') {
            throw new ProviderError('esewa', 'not configured');
        }

        return ['code' => $code, 'secret' => $secret];
    }

    public static function secret(): string
    {
        return self::creds()['secret'];
    }

    /**
     * @param  array{donation: array<string, mixed>, frequency: string, description: string, siteUrl: string}  $input
     * @return array{kind: 'form', action: string, fields: array<string, string>, providerReference: string}
     */
    public static function createForm(array $input): array
    {
        ['code' => $code, 'secret' => $secret] = self::creds();
        $d = $input['donation'];
        $amount = Signatures::nprMinorToEsewaAmount((int) $d['amount']);
        $fields = [
            'amount' => $amount,
            'tax_amount' => '0',
            'total_amount' => $amount,
            'transaction_uuid' => $d['reference'],
            'product_code' => $code,
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            // No query strings: eSewa appends "?data=…" to success_url.
            'success_url' => $input['siteUrl'].'/api/donations/esewa/success',
            'failure_url' => $input['siteUrl'].'/api/donations/esewa/failure/'.$d['reference'],
            'signed_field_names' => self::SIGNED_FIELDS,
        ];
        $fields['signature'] = Signatures::esewaSign($secret, $fields, self::SIGNED_FIELDS);

        return [
            'kind' => 'form',
            'action' => PaymentConfig::esewaForm(PaymentConfig::resolve()['ESEWA']['testMode']),
            'fields' => $fields,
            'providerReference' => $d['reference'],
        ];
    }

    /** @return array<string, mixed> */
    public static function status(string $reference, int $amountMinor): array
    {
        $code = self::creds()['code'];
        $res = Http::timeout(15)->get(PaymentConfig::esewaStatus(PaymentConfig::resolve()['ESEWA']['testMode']), [
            'product_code' => $code,
            'total_amount' => Signatures::nprMinorToEsewaAmount($amountMinor),
            'transaction_uuid' => $reference,
        ]);
        $json = $res->json();
        $json = is_array($json) ? $json : [];
        if (! $res->successful() && empty($json['status'])) {
            throw new ProviderError('esewa', 'status check failed ('.$res->status().')');
        }

        return $json;
    }
}
