<?php

declare(strict_types=1);

namespace App\Engagement\Forms;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Minimal RFC 4180 CSV writer with spreadsheet formula-injection protection (port of lib/forms/csv.ts):
 * cells starting with = + - @ TAB CR are prefixed with a single quote. UTF-8 BOM for Excel.
 */
final class Csv
{
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            $s = CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.v\Z');
        } elseif (is_bool($value)) {
            $s = $value ? 'true' : 'false';
        } else {
            $s = (string) $value;
            if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $s) === 1) {
                $s = "'".$s;
            }
        }
        if (preg_match('/[",\r\n]/', $s) === 1) {
            $s = '"'.str_replace('"', '""', $s).'"';
        }

        return $s;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     */
    public static function build(array $headers, iterable $rows): string
    {
        $lines = [implode(',', array_map(self::cell(...), $headers))];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(self::cell(...), $row));
        }

        return "\u{FEFF}".implode("\r\n", $lines)."\r\n";
    }

    public static function filename(string $prefix, ?DateTimeInterface $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $prefix.'-'.$now->format('Y-m-d').'.csv';
    }
}
