<?php

declare(strict_types=1);

use App\Engagement\Payments\Donations;
use App\Engagement\Payments\Signatures;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/* /api/donations + provider returns + webhooks: only server-side verification moves money states. */

function donationBody(array $extra = []): array
{
    return ['provider' => 'STRIPE', 'amount' => 25, 'consent' => true, 'locale' => 'it', 'startedAt' => (int) floor(microtime(true) * 1000) - 10_000, ...$extra];
}

function startDonation(array $extra = []): TestResponse
{
    return test()->postJson('/api/donations', donationBody($extra), ['Origin' => testOrigin()]);
}

beforeEach(function () {
    config(['sakila.site_url' => 'https://sakila.test']);
    Http::preventStrayRequests();
});

it("reports 'not configured' instead of failing when no keys are set", function () {
    startDonation()->assertStatus(409)->assertJson(['ok' => false, 'code' => 'NOT_CONFIGURED']);
    startDonation(['provider' => 'PAYPAL', 'frequency' => 'monthly'])->assertStatus(422)->assertJson(['fieldErrors' => ['frequency' => ['monthlyCardOnly']]]);
    expect(Donation::query()->count())->toBe(0);
});

it('Stripe: creates a PENDING donation and returns the hosted Checkout URL', function () {
    config(['engagement.payments.stripe.secret_key' => 'sk_test_123']);
    Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_abc', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc'])]);
    $res = startDonation(['donorEmail' => ' Donor@Example.it ', 'donorName' => 'Anna Rossi'])->assertStatus(201);
    expect($res->json('kind'))->toBe('redirect')->and($res->json('url'))->toBe('https://checkout.stripe.com/c/pay/cs_test_abc');
    $d = Donation::query()->sole();
    expect($d->status->value)->toBe('PENDING')->and($d->amount)->toBe(2500)->and($d->currency)->toBe('EUR')
        ->and($d->providerReference)->toBe('cs_test_abc')->and($d->isTestMode)->toBeTrue()
        ->and($d->donorEmail)->toBe('donor@example.it')->and($d->reference)->toBe($res->json('reference'));
    Http::assertSent(function ($request) use ($d) {
        parse_str($request->body(), $p);

        return $request->hasHeader('Idempotency-Key', "checkout-{$d->id}")
            && $p['mode'] === 'payment' && $p['client_reference_id'] === $d->id && $p['submit_type'] === 'donate'
            && $p['line_items'][0]['price_data']['unit_amount'] === '2500' && $p['customer_email'] === 'donor@example.it'
            && $p['success_url'] === 'https://sakila.test/api/donations/stripe/return?session_id={CHECKOUT_SESSION_ID}';
    });
});

it('a provider failure marks the donation FAILED and answers PROVIDER_ERROR (502)', function () {
    config(['engagement.payments.stripe.secret_key' => 'sk_test_123']);
    Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Invalid API Key']], 401)]);
    startDonation()->assertStatus(502)->assertJson(['code' => 'PROVIDER_ERROR']);
    expect(Donation::query()->sole()->status->value)->toBe('FAILED');
});

it('Stripe return: the session from Stripe decides; the URL alone never does', function () {
    config(['engagement.payments.stripe.secret_key' => 'sk_test_123']);
    $d = Donation::query()->create(['reference' => Donations::generateReference(), 'provider' => 'STRIPE', 'status' => 'PENDING', 'amount' => 2500, 'currency' => 'EUR', 'locale' => 'en', 'providerReference' => 'cs_test_paid', 'providerData' => ['frequency' => 'once']]);
    Http::fake(['api.stripe.com/v1/checkout/sessions/cs_test_paid' => Http::response(['id' => 'cs_test_paid', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 2500, 'currency' => 'eur', 'payment_intent' => 'pi_1', 'customer_details' => ['email' => 'x@y.it', 'name' => 'X Y']])]);
    $this->get('/api/donations/stripe/return?session_id=cs_test_paid')->assertRedirect("https://sakila.test/en/donate/thank-you?ref={$d->reference}");
    $d->refresh();
    expect($d->status->value)->toBe('SUCCEEDED')->and($d->completedAt)->not->toBeNull()
        ->and($d->providerData)->toMatchArray(['frequency' => 'once', 'paymentIntentId' => 'pi_1'])->and($d->donorEmail)->toBe('x@y.it');
    $this->get('/api/donations/stripe/return?session_id=cs_test_unknown')->assertRedirect('https://sakila.test/it/donate/cancelled');
});

it('a SUCCEEDED report with a different amount is stored as FAILED (tamper protection)', function () {
    $d = Donation::query()->create(['reference' => Donations::generateReference(), 'provider' => 'STRIPE', 'status' => 'PENDING', 'amount' => 2500, 'currency' => 'EUR', 'locale' => 'it', 'providerData' => ['frequency' => 'once']]);
    $r = Donations::applyVerified($d->id, ['outcome' => 'SUCCEEDED', 'amountMinor' => 100, 'currency' => 'EUR', 'data' => []]);
    expect($r)->toBe(['changed' => true, 'status' => 'FAILED'])->and($d->fresh()->providerData)->toMatchArray(['mismatch' => true, 'reportedAmount' => 100]);
});

it('applyVerified is idempotent and keeps campaign totals in sync (success, then refund)', function () {
    $c = Campaign::query()->create(['status' => 'PUBLISHED', 'raisedAmount' => 1000, 'currency' => 'EUR']);
    $d = Donation::query()->create(['reference' => Donations::generateReference(), 'provider' => 'PAYPAL', 'status' => 'PENDING', 'amount' => 2500, 'currency' => 'EUR', 'locale' => 'it', 'campaignId' => $c->id, 'providerData' => ['frequency' => 'once']]);
    $ok = ['outcome' => 'SUCCEEDED', 'amountMinor' => 2500, 'currency' => 'EUR', 'data' => ['orderId' => 'O1']];
    expect(Donations::applyVerified($d->id, $ok)['changed'])->toBeTrue()
        ->and(Donations::applyVerified($d->id, $ok)['changed'])->toBeFalse()
        ->and($c->fresh()->raisedAmount)->toBe(3500);
    Donations::applyVerified($d->id, ['outcome' => 'REFUNDED', 'data' => []]);
    expect($d->fresh()->status->value)->toBe('REFUNDED')->and($c->fresh()->raisedAmount)->toBe(1000);
});

it('Stripe webhook: verified, idempotent, records renewals and refunds', function () {
    config(['engagement.payments.stripe.webhook_secret' => 'whsec_test', 'engagement.payments.stripe.secret_key' => 'sk_test_123']);
    $d = Donation::query()->create(['reference' => Donations::generateReference(), 'provider' => 'STRIPE', 'status' => 'PENDING', 'amount' => 1000, 'currency' => 'EUR', 'locale' => 'it', 'providerReference' => 'cs_test_m', 'providerData' => ['frequency' => 'monthly']]);
    $send = function (array $event, ?string $sig = null) {
        $raw = json_encode($event);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig ?? Signatures::signStripe($raw, 'whsec_test', time())], $raw);
    };
    $completed = ['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_m', 'client_reference_id' => $d->id, 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 1000, 'currency' => 'eur', 'subscription' => 'sub_1', 'payment_intent' => null]]];
    $send($completed, 't=1,v1=bad')->assertStatus(400);
    $send($completed)->assertOk()->assertJson(['received' => true, 'result' => 'processed']);
    $send($completed)->assertOk()->assertJson(['result' => 'duplicate']);
    expect($d->fresh()->status->value)->toBe('SUCCEEDED')->and(PaymentWebhookEvent::query()->count())->toBe(1);

    $renewal = ['id' => 'evt_2', 'type' => 'invoice.paid', 'livemode' => false, 'data' => ['object' => ['id' => 'in_2', 'billing_reason' => 'subscription_cycle', 'parent' => ['subscription_details' => ['subscription' => 'sub_1']], 'amount_paid' => 1000, 'currency' => 'eur']]];
    $send($renewal)->assertOk();
    $send([...$renewal, 'id' => 'evt_3'])->assertOk(); // same invoice again → unique (provider, providerReference)
    $renewals = Donation::query()->where('providerReference', 'in_2')->get();
    expect($renewals)->toHaveCount(1)->and($renewals[0]->status->value)->toBe('SUCCEEDED')->and($renewals[0]->providerData['renewalOf'])->toBe($d->reference);

    Donation::query()->whereKey($d->id)->update(['providerData' => json_encode(['frequency' => 'once', 'paymentIntentId' => 'pi_9'])]);
    $send(['id' => 'evt_4', 'type' => 'charge.refunded', 'data' => ['object' => ['id' => 'ch_1', 'refunded' => true, 'payment_intent' => 'pi_9']]])->assertOk();
    expect($d->fresh()->status->value)->toBe('REFUNDED');
});

it('PayPal return captures server-side; eSewa success needs a valid HMAC and the status API', function () {
    config(['engagement.payments.paypal.client_id' => 'id', 'engagement.payments.paypal.client_secret' => 'secret',
        'engagement.payments.esewa.merchant_code' => 'EPAYTEST', 'engagement.payments.esewa.secret_key' => '8gBm/:&EnhH.1/q']);
    $pp = Donation::query()->create(['reference' => Donations::generateReference(), 'provider' => 'PAYPAL', 'status' => 'PENDING', 'amount' => 2500, 'currency' => 'EUR', 'locale' => 'it', 'providerReference' => 'ORDER12345', 'providerData' => ['frequency' => 'once']]);
    $es = Donation::query()->create(['reference' => 'SKL-2026-ABCDEFGH', 'provider' => 'ESEWA', 'status' => 'PENDING', 'amount' => 100_000, 'currency' => 'NPR', 'locale' => 'it', 'providerReference' => 'SKL-2026-ABCDEFGH', 'providerData' => ['frequency' => 'once']]);
    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'tok']),
        'api-m.sandbox.paypal.com/v2/checkout/orders/ORDER12345/capture' => Http::response(['id' => 'ORDER12345', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'C1', 'status' => 'COMPLETED', 'amount' => ['value' => '25.00', 'currency_code' => 'EUR']]]]]]]),
        'rc.esewa.com.np/*' => Http::response(['status' => 'COMPLETE', 'total_amount' => 1000.0, 'ref_id' => 'R1', 'transaction_uuid' => 'SKL-2026-ABCDEFGH']),
    ]);
    $this->get('/api/donations/paypal/return?token=ORDER12345')->assertRedirect("https://sakila.test/it/donate/thank-you?ref={$pp->reference}");
    expect($pp->fresh()->status->value)->toBe('SUCCEEDED');

    $fields = ['transaction_code' => '000AWEO', 'status' => 'COMPLETE', 'total_amount' => '1000.0', 'transaction_uuid' => 'SKL-2026-ABCDEFGH', 'product_code' => 'EPAYTEST', 'signed_field_names' => 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names'];
    $fields['signature'] = Signatures::esewaSign('8gBm/:&EnhH.1/q', $fields, $fields['signed_field_names']);
    $forged = base64_encode(json_encode([...$fields, 'signature' => 'AAAA']));
    $this->get('/api/donations/esewa/success?data='.rawurlencode($forged))->assertRedirect('https://sakila.test/it/donate/cancelled');
    expect($es->fresh()->status->value)->toBe('PENDING');
    $this->get('/api/donations/esewa/success?data='.rawurlencode(base64_encode(json_encode($fields))))
        ->assertRedirect('https://sakila.test/it/donate/thank-you?ref=SKL-2026-ABCDEFGH');
    expect($es->fresh()->status->value)->toBe('SUCCEEDED')->and($es->fresh()->providerData['transactionCode'])->toBe('000AWEO');
});

it('thank-you summary exposes no personal data and ignores malformed references', function () {
    $d = Donation::query()->create(['reference' => 'SKL-2026-ZZZZ2345', 'provider' => 'STRIPE', 'status' => 'SUCCEEDED', 'amount' => 2500, 'currency' => 'EUR', 'locale' => 'it', 'donorName' => 'Anna Rossi', 'donorEmail' => 'a@b.it', 'providerData' => ['frequency' => 'monthly']]);
    expect(Donations::summary('SKL-2026-ZZZZ2345'))->toMatchArray(['status' => 'SUCCEEDED', 'firstName' => 'Anna', 'frequency' => 'monthly', 'amount' => 2500])
        ->and(json_encode(Donations::summary('SKL-2026-ZZZZ2345')))->not->toContain('a@b.it')
        ->and(Donations::summary('SKL-2026-AAAAAAAA'))->toBeNull()
        ->and(Donations::summary("' OR 1=1 --"))->toBeNull();
    $d->update(['isAnonymous' => true]);
    expect(Donations::summary('SKL-2026-ZZZZ2345')['firstName'])->toBeNull();
});
