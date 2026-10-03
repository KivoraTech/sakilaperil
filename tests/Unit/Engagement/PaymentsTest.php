<?php

declare(strict_types=1);

use App\Engagement\Payments\Bank;
use App\Engagement\Payments\Interpret;
use App\Engagement\Payments\PaymentConfig;
use App\Engagement\Payments\Signatures;
use App\Engagement\Payments\Stripe;

/* Port of the reference tests/unit/engagement-payments.test.ts. */

// Published eSewa UAT secret (developer.esewa.com.np) — not a real credential.
const ESEWA_TEST_SECRET = '8gBm/:&EnhH.1/q';
const ESEWA_DOC_RESPONSE = '{"transaction_code":"000AWEO","status":"COMPLETE","total_amount":1000.0,"transaction_uuid":"250610-162413","product_code":"EPAYTEST","signed_field_names":"transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names","signature":"62GcfZTmVkzhtUeh+QJ1AqiJrjoWWGof3U+eTPTZ7fA="}';

describe('eSewa ePay v2 signatures', function () {
    it('matches the official form example (total_amount=110, uuid 241028)', function () {
        expect(Signatures::esewaSign(ESEWA_TEST_SECRET, ['total_amount' => '110', 'transaction_uuid' => '241028', 'product_code' => 'EPAYTEST'], 'total_amount,transaction_uuid,product_code'))
            ->toBe('i94zsd3oXF6ZsSr/kGqT4sSzYQzjj1W/waxjWyRwaME=');
    });

    it('verifies the official success response (numbers signed as sent, e.g. 1000.0)', function () {
        $decoded = Signatures::decodeEsewaCallback(base64_encode(ESEWA_DOC_RESPONSE), ESEWA_TEST_SECRET);
        expect($decoded['transaction_uuid'] ?? null)->toBe('250610-162413')
            ->and($decoded['status'] ?? null)->toBe('COMPLETE');
    });

    it('rejects tampered callbacks, wrong secrets and junk', function () {
        expect(Signatures::decodeEsewaCallback(base64_encode(str_replace('1000.0', '9000.0', ESEWA_DOC_RESPONSE)), ESEWA_TEST_SECRET))->toBeNull()
            ->and(Signatures::decodeEsewaCallback(base64_encode(str_replace('"COMPLETE"', '"PENDING"', ESEWA_DOC_RESPONSE)), ESEWA_TEST_SECRET))->toBeNull()
            ->and(Signatures::decodeEsewaCallback(base64_encode(ESEWA_DOC_RESPONSE), 'wrong-secret'))->toBeNull()
            ->and(Signatures::decodeEsewaCallback('not base64 json', ESEWA_TEST_SECRET))->toBeNull()
            ->and(Signatures::decodeEsewaCallback(null, ESEWA_TEST_SECRET))->toBeNull()
            ->and(Signatures::decodeEsewaCallback(base64_encode('{"signed_field_names":"a(b","signature":"x"}'), ESEWA_TEST_SECRET))->toBeNull();
    });

    it('formats and compares rupee amounts', function () {
        expect(Signatures::nprMinorToEsewaAmount(100_000))->toBe('1000')
            ->and(Signatures::nprMinorToEsewaAmount(1050))->toBe('10.50')
            ->and(Signatures::rupeesEqualMinor('1,000.0', 100_000))->toBeTrue()
            ->and(Signatures::rupeesEqualMinor(10.5, 1050))->toBeTrue()
            ->and(Signatures::rupeesEqualMinor('abc', 1))->toBeFalse();
    });
});

describe('Stripe webhook signatures', function () {
    $secret = 'whsec_test_fixture_secret';
    $body = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed']);
    $now = 1_780_000_000;

    it('accepts a valid signature inside the tolerance window', function () use ($secret, $body, $now) {
        expect(Signatures::verifyStripe($body, Signatures::signStripe($body, $secret, $now), $secret, $now + 10))->toBeTrue();
    });

    it('accepts when any of several v1 signatures matches (secret rotation)', function () use ($secret, $body, $now) {
        $good = explode(',', Signatures::signStripe($body, $secret, $now))[1];
        expect(Signatures::verifyStripe($body, "t={$now},v1=".str_repeat('0', 64).",{$good}", $secret, $now))->toBeTrue();
    });

    it('rejects wrong secret, modified body, replayed timestamp and malformed headers', function () use ($secret, $body, $now) {
        $header = Signatures::signStripe($body, $secret, $now);
        expect(Signatures::verifyStripe($body, $header, 'whsec_other', $now))->toBeFalse()
            ->and(Signatures::verifyStripe("{$body} ", $header, $secret, $now))->toBeFalse()
            ->and(Signatures::verifyStripe($body, $header, $secret, $now + 301))->toBeFalse()
            ->and(Signatures::verifyStripe($body, null, $secret, $now))->toBeFalse()
            ->and(Signatures::verifyStripe($body, 'v1=abc', $secret, $now))->toBeFalse()
            ->and(Signatures::verifyStripe($body, "t={$now}", $secret, $now))->toBeFalse();
    });
});

describe('provider payload interpretation', function () {
    it('Stripe: only complete + paid is a success; expired is cancelled', function () {
        $paid = Interpret::stripeSession([
            'id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 2500,
            'currency' => 'eur', 'payment_intent' => 'pi_1', 'mode' => 'payment',
            'customer_details' => ['email' => 'a@b.it', 'name' => 'Anna Rossi'],
        ]);
        expect($paid)->toMatchArray(['outcome' => 'SUCCEEDED', 'amountMinor' => 2500, 'currency' => 'EUR', 'donorEmail' => 'a@b.it'])
            ->and($paid['data']['paymentIntentId'])->toBe('pi_1')
            ->and(Interpret::stripeSession(['status' => 'complete', 'payment_status' => 'unpaid'])['outcome'])->toBe('PENDING')
            ->and(Interpret::stripeSession(['status' => 'open', 'payment_status' => 'unpaid'])['outcome'])->toBe('PENDING')
            ->and(Interpret::stripeSession(['status' => 'expired'])['outcome'])->toBe('CANCELED')
            ->and(Interpret::stripeSession(['status' => 'complete', 'payment_status' => 'paid', 'subscription' => 'sub_9'])['data']['subscriptionId'])->toBe('sub_9');
    });

    it('Stripe invoices: subscription id from old and new API shapes', function () {
        expect(Interpret::stripeInvoiceSubscriptionId(['subscription' => 'sub_old']))->toBe('sub_old')
            ->and(Interpret::stripeInvoiceSubscriptionId(['parent' => ['subscription_details' => ['subscription' => 'sub_new']]]))->toBe('sub_new')
            ->and(Interpret::stripeInvoiceSubscriptionId([]))->toBeNull();
    });

    it('PayPal: completed order + completed capture is a success with the captured amount', function () {
        $order = [
            'id' => '5O190127TN364715T', 'status' => 'COMPLETED',
            'payer' => ['email_address' => 'p@x.it', 'name' => ['given_name' => 'Luca', 'surname' => 'Bianchi']],
            'purchase_units' => [['payments' => ['captures' => [['id' => '3C679366HH908993F', 'status' => 'COMPLETED', 'amount' => ['value' => '25.00', 'currency_code' => 'EUR']]]]]],
        ];
        expect(Interpret::paypalOrder($order))->toMatchArray(['outcome' => 'SUCCEEDED', 'amountMinor' => 2500, 'currency' => 'EUR', 'donorName' => 'Luca Bianchi']);
        $order['purchase_units'][0]['payments']['captures'][0]['status'] = 'DECLINED';
        expect(Interpret::paypalOrder($order)['outcome'])->toBe('FAILED')
            ->and(Interpret::paypalOrder(['id' => 'X', 'status' => 'APPROVED', 'purchase_units' => []])['outcome'])->toBe('PENDING');
    });

    it('eSewa status API mapping', function () {
        expect(Interpret::esewaStatus(['status' => 'COMPLETE', 'total_amount' => 1000.0, 'ref_id' => 'R1']))->toMatchArray(['outcome' => 'SUCCEEDED', 'amountMinor' => 100_000, 'currency' => 'NPR'])
            ->and(Interpret::esewaStatus(['status' => 'NOT_FOUND'])['outcome'])->toBe('FAILED')
            ->and(Interpret::esewaStatus(['status' => 'CANCELED'])['outcome'])->toBe('CANCELED')
            ->and(Interpret::esewaStatus(['status' => 'AMBIGUOUS'])['outcome'])->toBe('PENDING')
            ->and(Interpret::esewaStatus(['status' => 'FULL_REFUND'])['outcome'])->toBe('REFUNDED')
            ->and(Interpret::esewaStatus([])['outcome'])->toBe('PENDING');
    });

    it('Khalti lookup mapping (only Completed is a success)', function () {
        expect(Interpret::khaltiLookup(['pidx' => 'abc', 'status' => 'Completed', 'total_amount' => 100_000]))->toMatchArray(['outcome' => 'SUCCEEDED', 'amountMinor' => 100_000]);
        foreach (['Pending' => 'PENDING', 'Initiated' => 'PENDING', 'User canceled' => 'CANCELED', 'Expired' => 'CANCELED', 'Refunded' => 'REFUNDED'] as $in => $out) {
            expect(Interpret::khaltiLookup(['status' => $in])['outcome'])->toBe($out);
        }
    });

    it('donation state machine is idempotent and one-directional', function () {
        expect(Interpret::canTransition('PENDING', 'SUCCEEDED'))->toBeTrue()
            ->and(Interpret::canTransition('SUCCEEDED', 'SUCCEEDED'))->toBeFalse()
            ->and(Interpret::canTransition('SUCCEEDED', 'FAILED'))->toBeFalse()
            ->and(Interpret::canTransition('SUCCEEDED', 'REFUNDED'))->toBeTrue()
            ->and(Interpret::canTransition('REFUNDED', 'SUCCEEDED'))->toBeFalse()
            ->and(Interpret::canTransition('CANCELED', 'SUCCEEDED'))->toBeTrue()
            ->and(Interpret::canTransition('PENDING', 'PENDING'))->toBeFalse();
    });
});

describe('payment configuration', function () {
    it('is "not configured" without keys', function () {
        $c = PaymentConfig::resolve([]);
        expect($c['STRIPE']['configured'] || $c['PAYPAL']['configured'] || $c['ESEWA']['configured'] || $c['KHALTI']['configured'])->toBeFalse();
    });

    it('detects Stripe test vs live keys and requires explicit live opt-in for others', function () {
        expect(PaymentConfig::resolve(['stripe' => ['secret_key' => 'sk_test_abc']])['STRIPE'])->toBe(['configured' => true, 'testMode' => true])
            ->and(PaymentConfig::resolve(['stripe' => ['secret_key' => 'sk_live_abc']])['STRIPE'])->toBe(['configured' => true, 'testMode' => false])
            ->and(PaymentConfig::resolve(['stripe' => ['secret_key' => 'garbage']])['STRIPE']['configured'])->toBeFalse()
            ->and(PaymentConfig::resolve(['paypal' => ['client_id' => 'a', 'client_secret' => 'b']])['PAYPAL'])->toBe(['configured' => true, 'testMode' => true])
            ->and(PaymentConfig::resolve(['khalti' => ['secret_key' => 'k', 'env' => 'live']])['KHALTI']['testMode'])->toBeFalse()
            ->and(PaymentConfig::resolve(['esewa' => ['merchant_code' => 'EPAYTEST']])['ESEWA']['configured'])->toBeFalse();
    });
});

describe('bank transfer helpers', function () {
    it('validates IBANs with mod-97 and formats them in groups of 4', function () {
        expect(Bank::isValidIban('IT60 X054 2811 1010 0000 0123 456'))->toBeTrue()
            ->and(Bank::isValidIban('GB82WEST12345698765432'))->toBeTrue()
            ->and(Bank::isValidIban('IT60X0542811101000000123457'))->toBeFalse()
            ->and(Bank::isValidIban('[[TODO: IBAN]]'))->toBeFalse()
            ->and(Bank::formatIban('it60x0542811101000000123456'))->toBe('IT60 X054 2811 1010 0000 0123 456');
    });

    it('builds an EPC069-12 payload', function () {
        expect(explode("\n", Bank::epcPayload('Associazione Esempio', 'IT60 X054 2811 1010 0000 0123 456', 'bppiitrrxxx', 25, 'Donazione liberale')))
            ->toBe(['BCD', '002', '1', 'SCT', 'BPPIITRRXXX', 'Associazione Esempio', 'IT60X0542811101000000123456', 'EUR25', '', '', 'Donazione liberale'])
            ->and(explode("\n", Bank::epcPayload('X', 'GB82WEST12345698765432'))[7])->toBe('');
    });

    it('renders a scannable SVG QR data URI', function () {
        $uri = Bank::qrDataUri(Bank::epcPayload('X', 'GB82WEST12345698765432'));
        expect($uri)->toStartWith('data:image/svg+xml;base64,');
        $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
        expect($svg)->toContain('<svg')->toContain('<path stroke="#000000" d="M');
    });
});

it('encodes Stripe params like the reference (a[b][c]=v, arrays indexed)', function () {
    $pairs = Stripe::form(['mode' => 'payment', 'metadata' => ['donationId' => 'd1'], 'line_items' => [['quantity' => 1, 'price_data' => ['unit_amount' => 2500]]], 'skip' => null]);
    expect(Stripe::encode($pairs))->toBe('mode=payment&metadata%5BdonationId%5D=d1&line_items%5B0%5D%5Bquantity%5D=1&line_items%5B0%5D%5Bprice_data%5D%5Bunit_amount%5D=2500');
});
