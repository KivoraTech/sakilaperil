<?php

declare(strict_types=1);

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Engagement\Housekeeping;
use App\Engagement\Http\PublicJson;
use App\Engagement\Newsletter\Newsletter;
use App\Engagement\Newsletter\Tokens;
use App\Engagement\View\Components;
use App\Models\ContactSubmission;
use App\Models\FormRateLimit;
use App\Models\NewsletterSubscriber;
use App\Models\PaymentWebhookEvent;
use App\Models\SiteSettings as SiteSettingsModel;
use App\Settings\SiteSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Regression tests for the production audit (engagement: public forms, newsletter, donations).
 * IDs refer to docs/audit (QA-xx, UX-xx, A11Y-xx, SEC-xx, CODE-xx, DB-xx).
 */

function auditFormBody(array $extra = []): array
{
    return [
        'name' => 'Anna Rossi', 'email' => 'anna@example.it', 'message' => 'Ciao', 'consent' => true,
        'locale' => 'it', 'startedAt' => (int) floor(microtime(true) * 1000) - 10_000, ...$extra,
    ];
}

function noPaymentKeys(): void
{
    config(['engagement.payments' => [
        'stripe' => ['secret_key' => '', 'webhook_secret' => ''],
        'paypal' => ['client_id' => '', 'client_secret' => '', 'env' => 'sandbox', 'webhook_id' => ''],
        'esewa' => ['merchant_code' => '', 'secret_key' => '', 'env' => 'test'],
        'khalti' => ['secret_key' => '', 'env' => 'test'],
    ]]);
}

// ── Donate widget follows what is really configured (UX-02, QA-03, CODE-04, CODE-07) ─────────────

it('renders a bank-only donate widget when no online provider is configured', function () {
    noPaymentKeys();
    $html = Components::donateWidget('it');

    expect(Components::onlinePaymentsAvailable())->toBeFalse()
        ->and(Components::donateWidgetIsBankOnly())->toBeTrue()
        ->and($html)->not->toContain('data-role="frequency"')   // no "Ogni mese — consigliato" switch
        ->and($html)->not->toContain('data-method=')             // nothing to choose
        ->and($html)->not->toContain('data-role="amount"')
        ->and(substr_count($html, 'data-testid="bank-transfer"'))->toBe(1);

    // Visible text only (the island props JSON is not text): no provider names, no card claims.
    $visible = strip_tags((string) preg_replace('#<script\b.*?</script>#s', '', $html));
    expect($visible)->not->toContain('Stripe')
        ->and($visible)->not->toContain('PayPal')
        ->and($visible)->not->toContain('carta')
        ->and($visible)->not->toContain('Ogni mese')
        ->and($visible)->not->toContain('Non ancora disponibile');
});

it('shows frequency, methods and a provider line naming only the configured providers', function () {
    noPaymentKeys();
    config(['engagement.payments.stripe.secret_key' => 'sk_test_123']);
    $html = Components::donateWidget('it');
    $visible = strip_tags((string) preg_replace('#<script\b.*?</script>#s', '', $html));

    expect(Components::onlinePaymentsAvailable())->toBeTrue()
        ->and(Components::donateWidgetIsBankOnly())->toBeFalse()
        ->and(substr_count($html, 'data-role="frequency"'))->toBe(2)
        ->and($html)->toContain('data-method="STRIPE"')
        ->and($html)->toContain('data-method="BANK"')
        ->and($html)->not->toContain('data-method="PAYPAL"')
        ->and($html)->not->toMatch('/data-role="frequency"[^>]*disabled/')
        ->and($visible)->toContain('I pagamenti online sono gestiti da Stripe:')
        ->and($visible)->not->toContain('PayPal');

    config(['engagement.payments.paypal.client_id' => 'id', 'engagement.payments.paypal.client_secret' => 'secret']);
    $both = strip_tags((string) preg_replace('#<script\b.*?</script>#s', '', Components::donateWidget('en')));
    expect($both)->toContain('Online payments are handled by Stripe or PayPal:');
});

it('never combines two background utilities on a method tile (class order must not matter)', function () {
    noPaymentKeys();
    config(['engagement.payments.stripe.secret_key' => 'sk_test_123']);
    preg_match_all('/<label[^>]*data-method="[A-Z]+"[^>]*class="([^"]*)"|<label[^>]*class="([^"]*)"[^>]*data-method="[A-Z]+"/', Components::donateWidget('it'), $m);
    $classes = array_filter(array_merge($m[1], $m[2]));
    expect($classes)->not->toBeEmpty();
    foreach ($classes as $class) {
        $bg = array_filter(explode(' ', $class), static fn (string $c): bool => str_starts_with($c, 'bg-'));
        expect(count($bg))->toBe(1);
    }
});

// ── Bank panel (A11Y-01, A11Y-03, A11Y-04, A11Y-12) ──────────────────────────────────────────────

it('gives every bank panel its own heading id, a configurable heading level and a valid dl', function () {
    $first = Components::bankTransferPanel('it');
    $second = Components::bankTransferPanel('it', null, 2);

    preg_match('/aria-labelledby="([^"]+)"/', $first, $a);
    preg_match('/aria-labelledby="([^"]+)"/', $second, $b);
    expect($a[1])->not->toBe($b[1])
        ->and($first)->toContain('<h3 id="'.$a[1].'"')
        ->and($second)->toContain('<h2 id="'.$b[1].'"');

    // dl > div > (dt + dd): the list holds nothing but name–value groups (the download link is outside).
    preg_match('#<dl[^>]*>(.*?)</dl>#s', $first, $dl);
    $children = preg_split('#(?=<div>)#', $dl[1], -1, PREG_SPLIT_NO_EMPTY);
    foreach ($children as $child) {
        expect($child)->toStartWith('<div><dt')->and($child)->toEndWith('</dd></div>');
    }
    expect($dl[1])->not->toContain('<a ');
});

it('keeps the page up when the bank details are wrong (invalid IBAN shows the notice, no QR)', function () {
    $this->seed(DatabaseSeeder::class);
    $row = SiteSettingsModel::query()->firstOrFail();
    $row->bankDetails = [...(array) $row->bankDetails, 'iban' => 'IT00 NOT AN IBAN', 'accountHolder' => 'Sakila per il Nepal ODV', 'qrImageId' => null];
    $row->save();
    TagCache::invalidate(CacheTags::SETTINGS);
    app()->forgetInstance(SiteSettings::class);

    $html = Components::bankTransferPanel('it');
    expect($html)->toContain('role="note"')
        ->and($html)->not->toContain('data-copy-value')
        ->and($html)->not->toContain('<img');
});

// ── Forms (UX-05, A11Y-05) ───────────────────────────────────────────────────────────────────────

it('prints the form introduction only together with the form title', function () {
    $withTitle = Components::submissionForm('volunteer', 'it');
    $embedded = Components::submissionForm('volunteer', 'it', 'block:abc', null, false);
    $intro = 'Raccontaci chi sei';

    expect($withTitle)->toContain($intro)
        ->and(strip_tags((string) preg_replace('#<script\b.*?</script>#s', '', $embedded)))->not->toContain($intro);
});

it('names the footer newsletter form differently from the others', function () {
    expect(Components::newsletterForm('it', 'footer'))->toContain('aria-label="Newsletter (piè di pagina)"')
        ->and(Components::newsletterForm('it', 'block:x'))->toContain('aria-label="Newsletter"');
});

it('no longer ships demo wording in the form messages', function () {
    foreach (['it', 'en'] as $locale) {
        $messages = (string) file_get_contents(resource_path("messages/{$locale}.json"));
        expect($messages)->not->toContain('Milan')
            ->and($messages)->not->toContain('Non ancora disponibile')
            ->and($messages)->not->toContain('Ristoranti, aziende e scuole');
    }
});

// ── Public API hardening (SEC-06, SEC-07, QA-13, QA-14) ──────────────────────────────────────────

it('does not answer CORS preflights for the public API', function () {
    $res = $this->call('OPTIONS', '/api/forms/contact', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($res->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

it('ignores a client-supplied X-Forwarded-Host in the same-origin check', function () {
    $this->postJson('/api/forms/contact', auditFormBody(), ['Origin' => 'https://evil.example', 'X-Forwarded-Host' => 'evil.example'])
        ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);
    expect(ContactSubmission::query()->count())->toBe(0);
});

it('answers wrong types with message keys, never the validator\'s English sentences', function () {
    $res = $this->postJson('/api/forms/contact', auditFormBody(['name' => 123]), ['Origin' => testOrigin()])->assertStatus(422);
    expect($res->json('fieldErrors.name'))->toBe(['invalid']);

    expect(PublicJson::publicFieldErrors(['a' => ['required'], 'b' => ['Invalid input: expected string, received number'], 'c' => ['invalidEmail', 'weird']]))
        ->toBe(['a' => ['required'], 'b' => ['invalid'], 'c' => ['invalidEmail', 'invalid']]);
});

it('does not count field-validation failures against the form rate limit', function () {
    for ($i = 0; $i < 8; $i++) {
        $this->postJson('/api/forms/contact', auditFormBody(['email' => 'not-an-email']), ['Origin' => testOrigin()])->assertStatus(422);
    }
    expect(FormRateLimit::query()->sole()->count)->toBe(0);
    $this->postJson('/api/forms/contact', auditFormBody(), ['Origin' => testOrigin()])->assertStatus(201);

    // Spam rejections still count.
    $this->postJson('/api/forms/contact', auditFormBody(['hp_extra' => 'http://spam']), ['Origin' => testOrigin()])->assertStatus(400);
    expect(FormRateLimit::query()->sole()->count)->toBe(2);
});

// ── Newsletter tokens and retention (SEC-14, DB-03) ──────────────────────────────────────────────

it('expires confirmation links after 30 days while unsubscribe links keep working', function () {
    $secret = str_repeat('s', 40);
    $now = 1_800_000_000;
    $token = Tokens::createTimed($secret, 'confirm', 'Anna@Example.it', $now);

    expect(Tokens::verifyTimed($secret, 'confirm', $token, 30 * 86400, $now + 29 * 86400))->toBe('anna@example.it')
        ->and(Tokens::verifyTimed($secret, 'confirm', $token, 30 * 86400, $now + 31 * 86400))->toBeNull()
        ->and(Tokens::verifyTimed($secret, 'unsubscribe', $token, 30 * 86400, $now))->toBeNull()
        ->and(Tokens::verifyTimed('other-secret', 'confirm', $token, 30 * 86400, $now))->toBeNull();

    // Changing the timestamp invalidates the signature.
    [$email, , $mac] = explode('.', $token);
    expect(Tokens::verifyTimed($secret, 'confirm', $email.'.'.base_convert((string) ($now + 86400 * 365), 10, 36).'.'.$mac, 30 * 86400, $now + 86400 * 365))->toBeNull();

    // The service only accepts timed tokens for "confirm"; an untimed one (old format) is rejected.
    expect(Newsletter::readToken('confirm', Newsletter::token('confirm', 'a@b.it')))->toBe('a@b.it')
        ->and(Newsletter::readToken('confirm', Newsletter::token('unsubscribe', 'a@b.it')))->toBeNull()
        ->and(Newsletter::readToken('unsubscribe', Newsletter::token('unsubscribe', 'a@b.it')))->toBe('a@b.it');
});

it('prunes sign-ups that were never confirmed, and nothing else', function () {
    $old = Carbon::now('UTC')->subDays(45);
    $make = function (string $email, string $status, ?string $provider, Carbon $at): void {
        $row = new NewsletterSubscriber(['email' => $email, 'locale' => 'it', 'status' => $status, 'provider' => $provider, 'consentAt' => $at]);
        $row->save();
        NewsletterSubscriber::query()->where('email', $email)->update(['updatedAt' => $at->format('Y-m-d H:i:s.v')]);
    };
    $make('stale@example.it', 'PENDING', 'smtp', $old);          // link expired → deleted
    $make('manual@example.it', 'PENDING', null, $old);            // no provider: never mailed → kept for manual sync
    $make('fresh@example.it', 'PENDING', 'smtp', Carbon::now('UTC')->subDays(3));
    $make('member@example.it', 'SUBSCRIBED', 'smtp', $old);
    $make('gone@example.it', 'UNSUBSCRIBED', 'smtp', $old);
    PaymentWebhookEvent::query()->create(['provider' => 'STRIPE', 'eventId' => 'evt_old', 'type' => 'x']);
    PaymentWebhookEvent::query()->where('eventId', 'evt_old')->update(['receivedAt' => Carbon::now('UTC')->subDays(200)->format('Y-m-d H:i:s.v')]);
    PaymentWebhookEvent::query()->create(['provider' => 'STRIPE', 'eventId' => 'evt_new', 'type' => 'x']);

    expect(Housekeeping::prune(dryRun: true))->toBe(['unconfirmedNewsletter' => 1, 'webhookEvents' => 1])
        ->and(NewsletterSubscriber::query()->count())->toBe(5);
    expect(Housekeeping::prune())->toBe(['unconfirmedNewsletter' => 1, 'webhookEvents' => 1]);

    expect(NewsletterSubscriber::query()->orderBy('email')->pluck('email')->all())
        ->toBe(['fresh@example.it', 'gone@example.it', 'manual@example.it', 'member@example.it'])
        ->and(PaymentWebhookEvent::query()->pluck('eventId')->all())->toBe(['evt_new']);
});

it('treats a simultaneous second sign-up for the same address as done, not as an error', function () {
    // Simulates the race: the row appears between the "exists?" check and the insert.
    NewsletterSubscriber::creating(function (NewsletterSubscriber $row): void {
        static $raced = false;
        if (! $raced && $row->email === 'race@example.it') {
            $raced = true;
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s.v');
            DB::table('newsletter_subscribers')->insert(['id' => 'race0000000000000000000001', 'email' => 'race@example.it', 'locale' => 'it', 'status' => 'PENDING', 'consentAt' => $now, 'updatedAt' => $now]);
        }
    });

    Newsletter::subscribe('race@example.it', 'it', 'footer');
    expect(NewsletterSubscriber::query()->where('email', 'race@example.it')->count())->toBe(1);
});
