<?php

declare(strict_types=1);

use App\Engagement\Forms\Csv;
use App\Engagement\Newsletter\Tokens;
use App\Engagement\Spam\SpamChecks;

/* Port of the reference tests/unit/engagement-forms.test.ts (schemas: ValidationGoldenTest). */

describe('spam: honeypot + time trap', function () {
    $now = 1_000_000_000;

    it('passes a human-looking submission', function () use ($now) {
        expect(SpamChecks::honeypotAndTiming('', $now - 10_000, $now))->toBe(['ok' => true])
            ->and(SpamChecks::honeypotAndTiming(null, $now - 10_000, $now))->toBe(['ok' => true]);
    });

    it('rejects a filled honeypot (including non-string values)', function () use ($now) {
        expect(SpamChecks::honeypotAndTiming('http://spam', $now - 10_000, $now))->toBe(['ok' => false, 'reason' => 'honeypot'])
            ->and(SpamChecks::honeypotAndTiming(['x'], $now - 10_000, $now)['ok'])->toBeFalse();
    });

    it('rejects too-fast, stale, future and missing timestamps', function () use ($now) {
        expect(SpamChecks::honeypotAndTiming('', $now - 500, $now))->toBe(['ok' => false, 'reason' => 'too_fast'])
            ->and(SpamChecks::honeypotAndTiming('', $now - 2 * 86_400_000, $now))->toBe(['ok' => false, 'reason' => 'stale'])
            ->and(SpamChecks::honeypotAndTiming('', null, $now))->toBe(['ok' => false, 'reason' => 'bad_timestamp'])
            ->and(SpamChecks::honeypotAndTiming('', 'abc', $now))->toBe(['ok' => false, 'reason' => 'bad_timestamp'])
            ->and(SpamChecks::honeypotAndTiming('', $now + 60_000, $now)['ok'])->toBeFalse();
    });
});

it('counts within the rate-limit window and resets after it', function () {
    $t0 = 1_790_000_000_000;
    $windowMs = SpamChecks::RATE_LIMITS['contact']['windowSec'] * 1000;
    $state = SpamChecks::nextWindow(null, $t0, $windowMs);
    expect($state['count'])->toBe(1);
    for ($i = 0; $i < SpamChecks::RATE_LIMITS['contact']['limit']; $i++) {
        $state = SpamChecks::nextWindow($state, $t0 + $i * 1000, $windowMs);
    }
    expect($state['count'])->toBe(SpamChecks::RATE_LIMITS['contact']['limit'] + 1)
        ->and(SpamChecks::nextWindow($state, $t0 + $windowMs, $windowMs))->toBe(['count' => 1, 'windowStart' => $t0 + $windowMs]);
});

describe('newsletter tokens', function () {
    $secret = str_repeat('x', 40);

    it('round-trips and binds the purpose', function () use ($secret) {
        $token = Tokens::create($secret, 'unsubscribe', 'Anna@Example.it');
        expect(Tokens::verify($secret, 'unsubscribe', $token))->toBe('anna@example.it')
            ->and(Tokens::verify($secret, 'confirm', $token))->toBeNull()
            ->and(Tokens::verify(str_repeat('y', 40), 'unsubscribe', $token))->toBeNull();
    });

    it('is compatible with the reference token format (base64url email . base64url HMAC)', function () use ($secret) {
        // Produced by the reference createNewsletterToken(secret, "confirm", "a@b.it").
        $mac = rtrim(strtr(base64_encode(hash_hmac('sha256', 'newsletter:confirm:a@b.it', $secret, true)), '+/', '-_'), '=');
        expect(Tokens::create($secret, 'confirm', 'a@b.it'))->toBe('YUBiLml0.'.$mac);
    });

    it('rejects tampered or malformed tokens', function () use ($secret) {
        $token = Tokens::create($secret, 'confirm', 'a@b.it');
        [, $mac] = explode('.', $token);
        $forged = rtrim(strtr(base64_encode('victim@b.it'), '+/', '-_'), '=').'.'.$mac;
        expect(Tokens::verify($secret, 'confirm', $forged))->toBeNull()
            ->and(Tokens::verify($secret, 'confirm', 'garbage'))->toBeNull()
            ->and(Tokens::verify($secret, 'confirm', 42))->toBeNull();
    });
});

describe('CSV export', function () {
    it('quotes separators and neutralises spreadsheet formulas', function () {
        expect(Csv::cell('He said "hi", then left'))->toBe('"He said ""hi"", then left"')
            ->and(Csv::cell('=HYPERLINK("http://evil")'))->toBe('"\'=HYPERLINK(""http://evil"")"')
            ->and(Csv::cell('+39 02 1234'))->toBe("'+39 02 1234")
            ->and(Csv::cell('@cmd'))->toBe("'@cmd")
            ->and(Csv::cell(-5))->toBe('-5')
            ->and(Csv::cell(null))->toBe('')
            ->and(Csv::cell(new DateTimeImmutable('2026-01-02T03:04:05Z')))->toBe('2026-01-02T03:04:05.000Z');
    });

    it('adds a BOM and CRLF line endings', function () {
        $csv = Csv::build(['a', 'b'], [['1', "x\ny"]]);
        expect($csv)->toStartWith("\u{FEFF}a,b\r\n")->toContain("1,\"x\ny\"\r\n");
    });
});
