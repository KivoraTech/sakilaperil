<?php

declare(strict_types=1);

use App\Audit\Audit;
use App\Auth\LoginThrottle;
use App\I18n\Locales;
use App\I18n\Routes;
use App\Storage\StorageKeys;
use App\Storage\UploadValidator;
use App\Support\Cuid;
use Carbon\CarbonImmutable;

it('generates Prisma-compatible cuids', function () {
    $ids = array_map(fn () => Cuid::make(), range(1, 200));
    expect(array_unique($ids))->toHaveCount(200);
    foreach ($ids as $id) {
        expect(Cuid::isValid($id))->toBeTrue();
    }
});

it('locks after the policy limit and reports retry-after', function () {
    $now = CarbonImmutable::parse('2026-10-01T10:00:00Z');
    $state = null;
    for ($i = 0; $i < 5; $i++) {
        $state = LoginThrottle::registerEvent($state, $now, LoginThrottle::EMAIL_POLICY);
    }
    expect(LoginThrottle::isLocked($state, $now))->toBeTrue()
        ->and(LoginThrottle::retryAfterSeconds($state, $now))->toBe(900)
        ->and(LoginThrottle::isLocked($state, $now->addMinutes(16)))->toBeFalse();
    $fresh = LoginThrottle::registerEvent($state, $now->addMinutes(16), LoginThrottle::EMAIL_POLICY);
    expect($fresh['count'])->toBe(1);
});

it('slugifies and localizes internal links like the reference', function () {
    expect(Routes::slugify('  Una Scuola per il Villaggio — Città!  '))->toBe('una-scuola-per-il-villaggio-citta')
        ->and(Routes::localize('/projects', 'en'))->toBe('/en/projects')
        ->and(Routes::localize('/', 'it'))->toBe('/it')
        ->and(Routes::localize('#dona', 'it'))->toBe('#dona')
        ->and(Routes::localize('/chi-siamo#team', 'it'))->toBe('/it/chi-siamo#team')
        ->and(Routes::localize('https://example.org/x', 'it'))->toBe('https://example.org/x')
        ->and(Routes::localize('/it/news', 'en'))->toBe('/it/news')
        ->and(Locales::pick(['it' => 'Ciao', 'en' => ''], 'en'))->toBe('Ciao')
        ->and(Locales::pick(null, 'en'))->toBe('');
});

it('validates storage keys and sniffs uploads by magic bytes', function () {
    expect(StorageKeys::isValid('2026/09/abc.jpg'))->toBeTrue()
        ->and(StorageKeys::isValid('seed/placeholder-annual-report.pdf'))->toBeTrue()
        ->and(StorageKeys::isValid('../etc/passwd'))->toBeFalse()
        ->and(StorageKeys::isValid('a//b.jpg'))->toBeFalse()
        ->and(StorageKeys::generate('JPG'))->toMatch('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#')
        ->and(StorageKeys::sanitizeFilename('../../x/My <photo>?.png'))->toBe('My photo.png');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    expect(UploadValidator::validate($png))->toMatchArray(['ok' => true, 'mimeType' => 'image/png', 'ext' => 'png'])
        ->and(UploadValidator::validate('<svg xmlns="http://www.w3.org/2000/svg"/>')['ok'])->toBeFalse()
        ->and(UploadValidator::validate('')['error'])->toBe('EMPTY')
        ->and(UploadValidator::validate("%PDF-1.4\n", ['IMAGE'])['ok'])->toBeFalse();
});

it('redacts secrets in audit metadata', function () {
    expect(Audit::sanitize(['password' => 'x', 'nested' => ['apiKey' => 'k', 'ok' => 1], 'long' => str_repeat('a', 2100)]))
        ->toMatchArray(['password' => '[redacted]', 'nested' => ['apiKey' => '[redacted]', 'ok' => 1]]);
});
