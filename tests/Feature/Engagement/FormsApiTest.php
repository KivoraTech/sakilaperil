<?php

declare(strict_types=1);

use App\Engagement\Newsletter\Newsletter;
use App\Models\ContactSubmission;
use App\Models\FormRateLimit;
use App\Models\NewsletterSubscriber;
use App\Models\PartnerInquiry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/* POST /api/forms/{type}, /api/newsletter/* — same contract as the reference route handlers. */

function formBody(array $extra = []): array
{
    return [
        'name' => 'Anna Rossi', 'email' => 'Anna@Example.it', 'message' => 'Ciao', 'consent' => true,
        'locale' => 'it', 'startedAt' => (int) floor(microtime(true) * 1000) - 10_000, 'source' => 'block:abc123', ...$extra,
    ];
}

function postForm(string $type, array $body, array $headers = []): TestResponse
{
    return test()->postJson("/api/forms/{$type}", $body, ['Origin' => testOrigin(), ...$headers]);
}

it('stores a contact message (201) with consent time and a hashed IP, never the raw IP', function () {
    postForm('contact', formBody(['phone' => '', 'subject' => '']))->assertStatus(201)->assertExactJson(['ok' => true])
        ->assertHeader('Cache-Control', 'no-store, private');
    $row = ContactSubmission::query()->sole();
    expect($row->email)->toBe('anna@example.it')
        ->and($row->phone)->toBeNull()
        ->and($row->subject)->toBeNull()
        ->and($row->ipHash)->toMatch('/^[0-9a-f]{32}$/')
        ->and($row->ipHash)->not->toContain('127.0.0.1')
        ->and($row->consentAt)->not->toBeNull();
});

it('rejects cross-site, too-fast, honeypot and invalid posts like the reference', function () {
    postForm('contact', formBody(), ['Origin' => 'https://evil.example'])->assertStatus(403)->assertJson(['ok' => false, 'code' => 'FORBIDDEN']);
    $this->postJson('/api/forms/contact', formBody())->assertStatus(403); // no Origin at all
    postForm('contact', formBody(['startedAt' => (int) floor(microtime(true) * 1000)]))->assertStatus(400)->assertJson(['code' => 'SPAM']);
    postForm('contact', formBody(['hp_extra' => 'http://spam']))->assertStatus(400)->assertJson(['code' => 'SPAM']);
    postForm('contact', formBody(['source' => '<script>']))->assertStatus(400)->assertJson(['code' => 'SPAM']);
    postForm('contact', formBody(['email' => 'not-an-email', 'consent' => false]))->assertStatus(422)
        ->assertJson(['code' => 'VALIDATION', 'fieldErrors' => ['email' => ['invalidEmail'], 'consent' => ['consentRequired']]]);
    postForm('unknown', formBody())->assertStatus(404)->assertJson(['code' => 'ERROR']);
    $this->call('POST', '/api/forms/contact', [], [], [], ['HTTP_ORIGIN' => testOrigin(), 'CONTENT_TYPE' => 'text/plain'], 'x')->assertStatus(400);
    expect(ContactSubmission::query()->count())->toBe(0);
});

it('rate-limits per hashed IP (5 contact messages / 10 min) with Retry-After, counting rejected attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        postForm('contact', formBody(['email' => "a{$i}@example.it"]))->assertStatus(201);
    }
    postForm('contact', formBody())->assertStatus(429)->assertJson(['code' => 'RATE_LIMITED'])->assertHeader('Retry-After');
    expect(FormRateLimit::query()->sole()->count)->toBe(6);
    // Other forms have their own windows.
    postForm('partner', ['organization' => 'Trattoria', 'contactName' => 'Mario', 'email' => 'm@t.it', 'message' => 'Ciao', 'consent' => true, 'website' => '', 'partnershipType' => 'restaurant', 'locale' => 'en', 'startedAt' => formBody()['startedAt']])
        ->assertStatus(201);
    expect(PartnerInquiry::query()->sole()->locale->value)->toBe('en');
});

it('newsletter: stores PENDING without enumeration, confirms with a signed token, unsubscribes', function () {
    $post = fn (array $b) => $this->postJson('/api/newsletter/subscribe', [...$b, 'locale' => 'it', 'startedAt' => formBody()['startedAt']], ['Origin' => testOrigin()]);
    $post(['email' => 'News@Example.org', 'consent' => true])->assertStatus(202)->assertExactJson(['ok' => true]);
    $post(['email' => 'news@example.org', 'consent' => true])->assertStatus(202);
    $post(['email' => 'bad', 'consent' => false])->assertStatus(422)->assertJson(['fieldErrors' => ['email' => ['invalidEmail'], 'consent' => ['consentRequired']]]);
    $row = NewsletterSubscriber::query()->sole();
    expect($row->status->value)->toBe('PENDING')->and($row->provider)->toBeNull();

    $this->get('/api/newsletter/confirm?token=garbage&locale=en')->assertRedirect(config('sakila.site_url').'/en/newsletter?status=invalid');
    $this->get('/api/newsletter/confirm?locale=it&token='.rawurlencode(Newsletter::token('confirm', 'news@example.org')))
        ->assertRedirect(config('sakila.site_url').'/it/newsletter?status=confirmed');
    expect($row->fresh()->status->value)->toBe('SUBSCRIBED')->and($row->fresh()->confirmedAt)->not->toBeNull();

    $token = Newsletter::token('unsubscribe', 'news@example.org');
    $this->get('/api/newsletter/unsubscribe?locale=it&token='.rawurlencode($token))->assertRedirect(); // GET never unsubscribes
    expect($row->fresh()->status->value)->toBe('SUBSCRIBED');
    $this->postJson('/api/newsletter/unsubscribe', ['token' => 'x.y'], ['Origin' => testOrigin()])->assertStatus(400);
    $this->postJson('/api/newsletter/unsubscribe', ['token' => $token], ['Origin' => testOrigin()])->assertOk();
    expect($row->fresh()->status->value)->toBe('UNSUBSCRIBED');
    // RFC 8058 one-click from mail clients (no Origin).
    $this->post('/api/newsletter/unsubscribe?token='.rawurlencode($token), ['List-Unsubscribe' => 'One-Click'])->assertOk();
});

it('newsletter: built-in SMTP double opt-in when mail is configured', function () {
    Mail::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.example.net', 'mail.from.address' => 'noreply@sakila.test']);
    $this->postJson('/api/newsletter/subscribe', ['email' => 'x@sakila.test', 'consent' => true, 'locale' => 'en', 'startedAt' => formBody()['startedAt']], ['Origin' => testOrigin()])
        ->assertStatus(202);
    expect(NewsletterSubscriber::query()->sole()->provider)->toBe('smtp');
});

it('notifies the team with a CMS link only (no personal data) when mail is configured', function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'noreply@sakila.test', 'engagement.notify_email_to' => 'team@sakila.test']);
    postForm('contact', formBody(['name' => 'Segreto Personale']))->assertStatus(201);
    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    $body = $messages[0]->getOriginalMessage()->getTextBody();
    expect($body)->toContain('/admin/submissions/contact/')->not->toContain('Segreto')->not->toContain('anna@example.it');
});

it('validates the RAW JSON body (TrimStrings/ConvertEmptyStringsToNull never apply)', function () {
    // hp_extra "" must stay "" (null would be a non-string honeypot → SPAM), and "   " message → required.
    postForm('contact', formBody(['hp_extra' => '', 'message' => '   ']))->assertStatus(422)
        ->assertJson(['fieldErrors' => ['message' => ['required']]]);
    postForm('contact', formBody(['hp_extra' => '', 'message' => "  Riga 1\n  Riga 2  "]))->assertStatus(201);
    expect(ContactSubmission::query()->sole()->message)->toBe("Riga 1\n  Riga 2");
});
