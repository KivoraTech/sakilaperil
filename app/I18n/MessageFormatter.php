<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * ICU MessageFormat subset (what messages/*.json uses): `{name}`, `{n, plural, =0 {…} one {# …} other {…}}`
 * and `{x, select, a {…} other {…}}`, nested. Plural rules for it/en: "one" when n is exactly 1.
 * Apostrophes are literal unless they quote `{`/`}` (ICU rule), so "L'importo" prints as is.
 * No intl extension needed.
 */
final class MessageFormatter
{
    /** @param array<string, mixed> $params */
    public static function format(string $pattern, array $params = []): string
    {
        $pos = 0;

        return self::parse($pattern, $pos, $params, null, false);
    }

    /** @param array<string, mixed> $params */
    private static function parse(string $s, int &$pos, array $params, int|float|null $pluralValue, bool $nested): string
    {
        $out = '';
        $len = strlen($s);
        while ($pos < $len) {
            $ch = $s[$pos];
            if ($ch === "'" && $pos + 1 < $len && in_array($s[$pos + 1], ['{', '}', "'"], true)) {
                if ($s[$pos + 1] === "'") {
                    $out .= "'";
                    $pos += 2;

                    continue;
                }
                $end = strpos($s, "'", $pos + 1);
                $end = $end === false ? $len : $end;
                $out .= substr($s, $pos + 1, $end - $pos - 1);
                $pos = $end + 1;

                continue;
            }
            if ($ch === '}' && $nested) {
                return $out;
            }
            if ($ch === '#' && $pluralValue !== null) {
                $out .= (string) $pluralValue;
                $pos++;

                continue;
            }
            if ($ch === '{') {
                $pos++;
                $out .= self::argument($s, $pos, $params);

                continue;
            }
            $out .= $ch;
            $pos++;
        }

        return $out;
    }

    /** @param array<string, mixed> $params */
    private static function argument(string $s, int &$pos, array $params): string
    {
        $close = self::findArgEnd($s, $pos);
        $header = substr($s, $pos, $close - $pos);
        $parts = array_map('trim', explode(',', $header, 3));
        $name = $parts[0];
        if (count($parts) === 1) {
            $pos = $close + 1;
            $value = $params[$name] ?? '{'.$name.'}';

            return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        }
        $type = $parts[1];
        // Options start after the second comma.
        $optStart = $pos + strpos($header, ',', strpos($header, ',') + 1) + 1;
        $pos = $optStart;
        $value = $params[$name] ?? null;
        $num = is_numeric($value) ? $value + 0 : 0;
        /** @var array<string, string> $options */
        $options = [];
        while (true) {
            while ($pos < strlen($s) && ctype_space($s[$pos])) {
                $pos++;
            }
            if ($pos >= strlen($s) || $s[$pos] === '}') {
                $pos++;
                break;
            }
            $keyEnd = strpos($s, '{', $pos);
            if ($keyEnd === false) {
                $pos = strlen($s);
                break;
            }
            $key = trim(substr($s, $pos, $keyEnd - $pos));
            $pos = $keyEnd + 1;
            $options[$key] = self::parse($s, $pos, $params, $type === 'plural' ? $num : null, true);
            $pos++; // closing brace of this option
        }
        if ($type === 'plural') {
            foreach ($options as $key => $text) {
                if (str_starts_with($key, '=') && (float) substr($key, 1) === (float) $num) {
                    return $text;
                }
            }
            $category = is_int($num) && $num === 1 ? 'one' : 'other';

            return $options[$category] ?? $options['other'] ?? '';
        }

        return $options[(string) $value] ?? $options['other'] ?? '';
    }

    /** Position of the comma-section end: first `}` or the `{` that starts options at depth 0. */
    private static function findArgEnd(string $s, int $pos): int
    {
        $depth = 0;
        for ($i = $pos; $i < strlen($s); $i++) {
            if ($s[$i] === '{') {
                $depth++;
            } elseif ($s[$i] === '}') {
                if ($depth === 0) {
                    return $i;
                }
                $depth--;
            }
        }

        return strlen($s);
    }
}
