<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

/**
 * Pure interpreters: provider API payload → our outcome (port of lib/payments/interpret.ts).
 * Only these decide whether money moved; redirect query params never do.
 *
 * A "verified" result: ['outcome' => SUCCEEDED|PENDING|FAILED|CANCELED|REFUNDED, 'amountMinor' => ?int,
 * 'currency' => ?string, 'data' => array<string, scalar|null>, 'donorEmail' => ?string, 'donorName' => ?string]
 */
final class Interpret
{
    private static function str(mixed $v): ?string
    {
        return is_string($v) ? $v : null;
    }

    private static function num(mixed $v): int|float|null
    {
        return (is_int($v) || is_float($v)) && is_finite((float) $v) ? $v : null;
    }

    /**
     * @param  array<string, mixed>  $s  Stripe Checkout Session
     * @return array<string, mixed>
     */
    public static function stripeSession(array $s): array
    {
        $status = self::str($s['status'] ?? null); // open | complete | expired
        $paymentStatus = self::str($s['payment_status'] ?? null); // paid | unpaid | no_payment_required
        $details = is_array($s['customer_details'] ?? null) ? $s['customer_details'] : [];
        $subscription = $s['subscription'] ?? null;
        $outcome = 'PENDING';
        if ($status === 'complete' && $paymentStatus === 'paid') {
            $outcome = 'SUCCEEDED';
        } elseif ($status === 'expired') {
            $outcome = 'CANCELED';
        }
        $amount = self::num($s['amount_total'] ?? null);
        $currency = self::str($s['currency'] ?? null);

        return [
            'outcome' => $outcome,
            'amountMinor' => $amount !== null ? (int) $amount : null,
            'currency' => $currency !== null ? strtoupper($currency) : null,
            'data' => [
                'sessionId' => self::str($s['id'] ?? null),
                'paymentIntentId' => self::str($s['payment_intent'] ?? null),
                'subscriptionId' => is_string($subscription) ? $subscription : self::str(is_array($subscription) ? ($subscription['id'] ?? null) : null),
                'status' => $status,
                'paymentStatus' => $paymentStatus,
                'mode' => self::str($s['mode'] ?? null),
            ],
            'donorEmail' => self::str($details['email'] ?? null),
            'donorName' => self::str($details['name'] ?? null),
        ];
    }

    /**
     * Subscription id of a Stripe invoice (pre-2025 `invoice.subscription`, 2025+ `parent.subscription_details`).
     *
     * @param  array<string, mixed>  $invoice
     */
    public static function stripeInvoiceSubscriptionId(array $invoice): ?string
    {
        if (is_string($invoice['subscription'] ?? null)) {
            return $invoice['subscription'];
        }
        $nested = $invoice['parent']['subscription_details']['subscription'] ?? null;
        if (is_string($nested)) {
            return $nested;
        }
        if (is_array($nested) && is_string($nested['id'] ?? null)) {
            return $nested['id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $order  PayPal Orders v2 order / capture response
     * @return array<string, mixed>
     */
    public static function paypalOrder(array $order): array
    {
        $units = is_array($order['purchase_units'] ?? null) ? $order['purchase_units'] : [];
        $unit = is_array($units[0] ?? null) ? $units[0] : [];
        $captures = is_array($unit['payments']['captures'] ?? null) ? $unit['payments']['captures'] : [];
        $capture = is_array($captures[0] ?? null) ? $captures[0] : null;
        $amount = $capture['amount'] ?? $unit['amount'] ?? null;
        $orderStatus = self::str($order['status'] ?? null);
        $captureStatus = self::str($capture['status'] ?? null);
        $outcome = 'PENDING';
        if ($orderStatus === 'COMPLETED' && $captureStatus === 'COMPLETED') {
            $outcome = 'SUCCEEDED';
        } elseif ($captureStatus === 'DECLINED' || $captureStatus === 'FAILED') {
            $outcome = 'FAILED';
        } elseif ($captureStatus === 'REFUNDED') {
            $outcome = 'REFUNDED';
        } elseif ($orderStatus === 'VOIDED') {
            $outcome = 'CANCELED';
        }
        $value = is_array($amount) && is_string($amount['value'] ?? null) && is_numeric($amount['value']) ? (float) $amount['value'] : null;
        $payer = is_array($order['payer'] ?? null) ? $order['payer'] : [];
        $name = implode(' ', array_filter([
            self::str($payer['name']['given_name'] ?? null),
            self::str($payer['name']['surname'] ?? null),
        ], static fn ($x) => $x !== null && $x !== ''));

        return [
            'outcome' => $outcome,
            'amountMinor' => $value !== null ? (int) round($value * 100) : null,
            'currency' => is_array($amount) ? self::str($amount['currency_code'] ?? null) : null,
            'data' => [
                'orderId' => self::str($order['id'] ?? null),
                'orderStatus' => $orderStatus,
                'captureId' => self::str($capture['id'] ?? null),
                'captureStatus' => $captureStatus,
            ],
            'donorEmail' => self::str($payer['email_address'] ?? null),
            'donorName' => $name !== '' ? $name : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $res  eSewa status API response
     * @return array<string, mixed>
     */
    public static function esewaStatus(array $res): array
    {
        $status = self::str($res['status'] ?? null);
        $map = [
            'COMPLETE' => 'SUCCEEDED', 'PENDING' => 'PENDING', 'AMBIGUOUS' => 'PENDING', 'FULL_REFUND' => 'REFUNDED',
            'PARTIAL_REFUND' => 'SUCCEEDED', 'NOT_FOUND' => 'FAILED', 'CANCELED' => 'CANCELED',
        ];
        $total = $res['total_amount'] ?? null;
        $value = is_int($total) || is_float($total) ? (float) $total : (is_string($total) && is_numeric($total) ? (float) $total : null);

        return [
            'outcome' => ($status !== null ? ($map[$status] ?? null) : null) ?? 'PENDING',
            'amountMinor' => $value !== null ? (int) round($value * 100) : null,
            'currency' => 'NPR',
            'data' => [
                'status' => $status,
                'refId' => self::str($res['ref_id'] ?? null),
                'transactionUuid' => self::str($res['transaction_uuid'] ?? null),
            ],
            'donorEmail' => null,
            'donorName' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $res  Khalti lookup response
     * @return array<string, mixed>
     */
    public static function khaltiLookup(array $res): array
    {
        $status = self::str($res['status'] ?? null);
        $map = [
            'Completed' => 'SUCCEEDED', 'Pending' => 'PENDING', 'Initiated' => 'PENDING', 'Refunded' => 'REFUNDED',
            'Partially Refunded' => 'SUCCEEDED', 'Expired' => 'CANCELED', 'User canceled' => 'CANCELED',
        ];
        $amount = self::num($res['total_amount'] ?? null);

        return [
            'outcome' => ($status !== null ? ($map[$status] ?? null) : null) ?? 'PENDING',
            'amountMinor' => $amount !== null ? (int) $amount : null, // paisa
            'currency' => 'NPR',
            'data' => [
                'pidx' => self::str($res['pidx'] ?? null),
                'status' => $status,
                'transactionId' => self::str($res['transaction_id'] ?? null),
                'refunded' => ($res['refunded'] ?? null) === true,
            ],
            'donorEmail' => null,
            'donorName' => null,
        ];
    }

    /** PENDING → anything; SUCCEEDED → REFUNDED; FAILED/CANCELED → SUCCEEDED (late success); else final. */
    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return false;
        }

        return match ($from) {
            'PENDING' => $to !== 'PENDING',
            'SUCCEEDED' => $to === 'REFUNDED',
            'FAILED', 'CANCELED' => $to === 'SUCCEEDED',
            default => false,
        };
    }
}
