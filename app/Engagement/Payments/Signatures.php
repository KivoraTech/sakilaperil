<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

/** Pure signature helpers for payment providers (port of lib/payments/signatures.ts). */
final class Signatures
{
    /**
     * Verify a `Stripe-Signature` header ("t=…,v1=…[,v1=…]") against the RAW body:
     * HMAC-SHA256(secret, "{t}.{body}") hex; rejects timestamps outside the tolerance (replay).
     */
    public static function verifyStripe(string $rawBody, ?string $header, string $secret, ?int $nowSec = null, int $toleranceSec = 300): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }
        $t = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part));
            if ($kv[0] === 't') {
                $t ??= $kv[1] ?? null;
            } elseif ($kv[0] === 'v1') {
                $signatures[] = $kv[1] ?? '';
            }
        }
        if ($t === null || preg_match('/^\d+$/', $t) !== 1 || $signatures === []) {
            return false;
        }
        $now = $nowSec ?? time();
        if (abs($now - (int) $t) > $toleranceSec) {
            return false;
        }
        $expected = hash_hmac('sha256', "{$t}.{$rawBody}", $secret);
        foreach ($signatures as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }

        return false;
    }

    /** Build a Stripe-Signature header (tests / local webhook simulation). */
    public static function signStripe(string $rawBody, string $secret, int $timestampSec): string
    {
        return "t={$timestampSec},v1=".hash_hmac('sha256', "{$timestampSec}.{$rawBody}", $secret);
    }

    /**
     * eSewa: base64(HMAC-SHA256(secret, "f1=v1,f2=v2,…")) over `signed_field_names`, in order.
     *
     * @param  array<string, string|int|float>  $fields
     */
    public static function esewaSign(string $secret, array $fields, string $signedFieldNames): string
    {
        $message = implode(',', array_map(
            static fn (string $name): string => $name.'='.($fields[$name] ?? ''),
            explode(',', $signedFieldNames),
        ));

        return base64_encode(hash_hmac('sha256', $message, $secret, true));
    }

    /**
     * Decode eSewa's base64 `data` callback and verify its signature. A valid signature proves eSewa
     * produced the message, but the status API still decides the outcome (ADR-008).
     *
     * @return array<string, mixed>|null
     */
    public static function decodeEsewaCallback(mixed $data, string $secret): ?array
    {
        if (! is_string($data) || strlen($data) > 4096) {
            return null;
        }
        $json = base64_decode($data, false);
        try {
            $p = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($p) || array_is_list($p)) {
            return null;
        }
        $names = is_string($p['signed_field_names'] ?? null) ? $p['signed_field_names'] : '';
        if ($names === '' || ! is_string($p['signature'] ?? null)) {
            return null;
        }
        if (preg_match('/^[a-z_]+(,[a-z_]+)*$/', $names) !== 1) {
            return null;
        }
        $values = [];
        foreach (explode(',', $names) as $name) {
            $v = $p[$name] ?? null;
            if ($v === null) {
                return null;
            }
            if (is_int($v) || is_float($v)) {
                // eSewa signs the TEXT as sent (e.g. "1000.0"): read numbers from the raw JSON token.
                $values[$name] = preg_match('/"'.preg_quote($name, '/').'"\s*:\s*(-?[0-9][0-9.eE+-]*)/', $json, $m) === 1 ? $m[1] : (string) $v;
            } elseif (is_bool($v)) {
                $values[$name] = $v ? 'true' : 'false';
            } elseif (is_string($v)) {
                $values[$name] = $v;
            } else {
                $values[$name] = (string) json_encode($v);
            }
        }
        if (! hash_equals(self::esewaSign($secret, $values, $names), $p['signature'])) {
            return null;
        }
        if (! is_string($p['transaction_uuid'] ?? null) || ! is_string($p['product_code'] ?? null)) {
            return null;
        }

        return $p;
    }

    /** eSewa amounts are rupees with up to 2 decimals. */
    public static function nprMinorToEsewaAmount(int $minor): string
    {
        return $minor % 100 === 0 ? (string) intdiv($minor, 100) : number_format($minor / 100, 2, '.', '');
    }

    public static function rupeesEqualMinor(string|int|float $rupees, int $minor): bool
    {
        $n = is_string($rupees) ? (float) str_replace(',', '', $rupees) : (float) $rupees;
        if (is_string($rupees) && ! is_numeric(str_replace(',', '', $rupees))) {
            return false;
        }

        return is_finite($n) && (int) round($n * 100) === $minor;
    }
}
