<?php

declare(strict_types=1);

use App\I18n\MessageFormatter as M;

it('interpolates, pluralises and selects like next-intl', function () {
    $p = '{count, plural, =0 {Nessun progetto} one {# progetto} other {# progetti}}';
    expect(M::format($p, ['count' => 0]))->toBe('Nessun progetto')
        ->and(M::format($p, ['count' => 1]))->toBe('1 progetto')
        ->and(M::format($p, ['count' => 7]))->toBe('7 progetti')
        ->and(M::format('{photos, plural, one {# foto} other {# foto}}{videos, plural, =0 {} one { · # video} other { · # video}}', ['photos' => 3, 'videos' => 0]))->toBe('3 foto')
        ->and(M::format("L'importo deve essere tra {min} e {max}", ['min' => '5 €', 'max' => '10 €']))->toBe("L'importo deve essere tra 5 € e 10 €")
        ->and(M::format('{g, select, f {lei} other {loro}}', ['g' => 'x']))->toBe('loro')
        ->and(M::format('Ciao {name}', []))->toBe('Ciao {name}');
});

it('translates with the reference dictionaries and returns the key when missing', function () {
    expect(t('projects.count', ['count' => 2], 'it'))->toBe('2 progetti')
        ->and(t('projects.count', ['count' => 1], 'en'))->toContain('1')
        ->and(t('does.not.exist', [], 'it'))->toBe('does.not.exist');
});
