<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\HtmlString;

/**
 * Small markup helpers that mirror how the reference React components serialise attributes.
 */
final class Html
{
    /** Plain class joiner (components/site/ui/cx.ts): falsy parts dropped, NOT tailwind-merge. */
    public static function cx(string|false|null ...$parts): string
    {
        return implode(' ', array_filter($parts, static fn ($p): bool => $p !== null && $p !== false && $p !== ''));
    }

    /**
     * Attributes like React renders them: null/false omitted, `true` → `""` for boolean HTML
     * attributes and `"true"` for aria-* / data-*, arrays for `style` → `a:b;c:d`.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function attrs(array $attrs): HtmlString
    {
        $out = [];
        foreach ($attrs as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($name === 'style' && is_array($value)) {
                $value = self::style($value);
                if ($value === '') {
                    continue;
                }
            }
            if ($value === true) {
                $value = str_starts_with($name, 'aria-') || str_starts_with($name, 'data-') ? 'true' : '';
            }
            $out[] = $name.'="'.e((string) $value).'"';
        }

        return new HtmlString($out === [] ? '' : ' '.implode(' ', $out));
    }

    /**
     * React style object → CSS text (`{"--i": 2, "--d": "300ms"}` → `--i:2;--d:300ms`).
     *
     * @param  array<string, string|int|float|null>  $style
     */
    public static function style(array $style): string
    {
        $parts = [];
        foreach ($style as $prop => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $prop.':'.(is_float($value) ? self::num($value) : (string) $value);
        }

        return implode(';', $parts);
    }

    /** JS number → string (1.0 → "1", 0.25 → "0.25"). */
    public static function num(int|float $n): string
    {
        if (is_int($n) || floor($n) === $n && abs($n) < 1e15) {
            return (string) (int) $n;
        }
        $s = rtrim(rtrim(sprintf('%.10F', $n), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    /**
     * `<script type="application/ld+json">` like components/site/seo/json-ld.tsx: JSON.stringify
     * semantics (null/undefined fields dropped from objects) and `<` escaped.
     *
     * @param  array<mixed>  $data
     */
    public static function jsonLd(array $data): HtmlString
    {
        $json = json_encode(self::dropNulls($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return new HtmlString('<script type="application/ld+json">'.str_replace('<', '\\u003c', $json).'</script>');
    }

    private static function dropNulls(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::dropNulls(...), $value);
        }
        $out = [];
        foreach ($value as $k => $v) {
            if ($v !== null) {
                $out[$k] = self::dropNulls($v);
            }
        }

        return $out;
    }
}
