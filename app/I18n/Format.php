<?php

declare(strict_types=1);

namespace App\I18n;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Port of sakila/components/site/ui/format.ts. Reproduces the browser/Node `Intl` output for
 * it-IT and en-GB byte-for-byte (non-breaking spaces, CLDR grouping, month names) WITHOUT the
 * PHP intl extension, which many shared hosts lack. Golden tests: tests/Fixtures/intl-golden.json.
 */
final class Format
{
    private const NBSP = "\u{00A0}";

    private const MONTHS = [
        'it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    private const MONTHS_SHORT = [
        'it' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec'],
    ];

    private const WEEKDAYS = [
        'it' => ['domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'],
        'en' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
    ];

    /** Symbols per locale (CLDR); unknown codes print the ISO code. */
    private const SYMBOLS = [
        'it' => ['EUR' => '€'],
        'en' => ['EUR' => '€', 'USD' => 'US$', 'GBP' => '£'],
    ];

    /** Money from MINOR units (cents / paisa). Never hand-format (design §8.7). */
    public static function money(int|float $minor, string $currency, string $locale, int $fractionDigits = 0): string
    {
        $number = self::number($minor / 100, $locale, $fractionDigits);
        $negative = str_starts_with($number, '-');
        $abs = $negative ? substr($number, 1) : $number;
        $symbol = self::SYMBOLS[$locale][strtoupper($currency)] ?? strtoupper($currency);
        if ($locale === 'it') {
            $out = $abs.self::NBSP.$symbol;
        } else {
            $out = preg_match('/\p{L}$/u', $symbol) === 1 ? $symbol.self::NBSP.$abs : $symbol.$abs;
        }

        return ($negative ? '-' : '').$out;
    }

    /** Money from whole units (e.g. donation presets in euros). */
    public static function wholeMoney(int|float $amount, string $currency, string $locale): string
    {
        return self::money($amount * 100, $currency, $locale);
    }

    /** Intl.NumberFormat default (max 3 fraction digits) or with an explicit maximum. */
    public static function number(int|float $value, string $locale, int $maxFractionDigits = 3): string
    {
        $rounded = self::roundHalfExpand((float) $value, $maxFractionDigits);
        $negative = $rounded < 0;
        $fixed = number_format(abs($rounded), $maxFractionDigits, '.', '');
        [$int, $frac] = array_pad(explode('.', $fixed), 2, '');
        $frac = rtrim($frac, '0');
        $decimal = $locale === 'it' ? ',' : '.';
        $group = $locale === 'it' ? '.' : ',';
        // CLDR minimumGroupingDigits: 2 for Italian (1000 → "1000", 10000 → "10.000"), 1 for English.
        $minDigitsToGroup = $locale === 'it' ? 5 : 4;
        if (strlen($int) >= $minDigitsToGroup) {
            $int = strrev(implode($group, str_split(strrev($int), 3)));
        }
        $out = $int.($frac !== '' ? $decimal.$frac : '');

        return ($negative && $out !== '0' ? '-' : '').$out;
    }

    /**
     * Intl.DateTimeFormat subset used by the site. Default: day numeric + month long + year.
     *
     * @param  array<string, string>|null  $opts  keys: weekday(long), day(numeric), month(long|short), year(numeric)
     */
    public static function date(DateTimeInterface|string $value, string $locale, ?array $opts = null, string $timeZone = 'Europe/Rome'): string
    {
        $opts ??= ['day' => 'numeric', 'month' => 'long', 'year' => 'numeric'];
        $d = self::toZone($value, $timeZone);
        $loc = $locale === 'it' ? 'it' : 'en';
        $parts = [];
        if (isset($opts['weekday'])) {
            $parts[] = self::WEEKDAYS[$loc][(int) $d->format('w')];
        }
        if (isset($opts['day'])) {
            $parts[] = $d->format('j');
        }
        if (isset($opts['month'])) {
            $idx = (int) $d->format('n') - 1;
            $parts[] = $opts['month'] === 'short' ? self::MONTHS_SHORT[$loc][$idx] : self::MONTHS[$loc][$idx];
        }
        if (isset($opts['year'])) {
            $parts[] = $d->format('Y');
        }

        return implode(' ', $parts);
    }

    public static function time(DateTimeInterface|string $value, string $locale, string $timeZone = 'Europe/Rome'): string
    {
        return self::toZone($value, $timeZone)->format('H:i');
    }

    public static function fileSize(int $bytes, string $locale): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return self::number($bytes / 1024, $locale, 1).' KB';
        }

        return self::number($bytes / (1024 * 1024), $locale, 1).' MB';
    }

    /** Rough reading time from a word count (200 wpm), at least 1 minute. */
    public static function readingMinutes(int $words): int
    {
        return max(1, (int) round($words / 200));
    }

    private static function toZone(DateTimeInterface|string $value, string $timeZone): DateTimeImmutable
    {
        $d = is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : DateTimeImmutable::createFromInterface($value);

        return $d->setTimezone(new DateTimeZone($timeZone));
    }

    private static function roundHalfExpand(float $value, int $digits): float
    {
        return round($value, $digits, PHP_ROUND_HALF_UP);
    }
}
