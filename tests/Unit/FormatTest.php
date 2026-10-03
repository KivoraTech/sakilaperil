<?php

declare(strict_types=1);

use App\I18n\Format;

/** Golden outputs captured from Node's Intl (it-IT / en-GB): PHP must match byte-for-byte. */
$golden = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/intl-golden.json'), true);

it('formats money exactly like Intl', function (array $c) {
    expect(Format::money($c['minor'], $c['cur'], $c['loc'], $c['fd']))->toBe($c['v']);
})->with(array_map(fn ($c) => [$c], $golden['money']));

it('formats numbers exactly like Intl', function (array $c) {
    expect(Format::number($c['n'], $c['loc']))->toBe($c['v']);
})->with(array_map(fn ($c) => [$c], $golden['number']));

it('formats dates exactly like Intl', function (array $c) {
    expect(Format::date($c['iso'], $c['loc'], $c['opts'], $c['tz']))->toBe($c['v']);
})->with(array_map(fn ($c) => [$c], $golden['date']));

it('formats times exactly like Intl', function (array $c) {
    expect(Format::time($c['iso'], $c['loc'], $c['tz']))->toBe($c['v']);
})->with(array_map(fn ($c) => [$c], $golden['time']));
