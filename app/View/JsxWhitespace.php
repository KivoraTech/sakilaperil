<?php

declare(strict_types=1);

namespace App\View;

/**
 * Blade templates that port React components must produce the SAME DOM as the reference app.
 * React (JSX) never emits whitespace text nodes between elements that are written on separate
 * lines, while Blade copies every newline + indentation into the HTML — which changes layout of
 * inline / inline-flex content and the DOM the parity harness compares.
 *
 * This pre-compiler applies the JSX whitespace rules to the TEMPLATE SOURCE (never to runtime
 * data such as CMS text):
 *   - a whitespace run that contains a line break is removed when it touches a tag (`>` before or
 *     `<` after), a Blade echo/comment (`}` before or `{` after) or a Blade directive (`@` after);
 *   - any other line break (between two words of text) becomes a single space;
 *   - explicit spaces (`{{ ' ' }}`, `&#32;`, a space on the same line) are kept.
 * Conventions in compacted templates: keep a plain-text run on the same line as its neighbours and
 * never put plain text directly after a bare directive (`@else`, `@endif`) — wrap it in a tag or `{{ }}`.
 *
 * Applied to resources/views/site/**, resources/views/components/site/**, the public document/site
 * layouts, and any template that contains the marker comment `{{-- @jsx --}}`.
 */
final class JsxWhitespace
{
    public const MARKER = '{{-- @jsx --}}';

    public static function applies(?string $path, string $source): bool
    {
        if (str_contains($source, self::MARKER)) {
            return true;
        }
        if ($path === null || $path === '') {
            return false;
        }
        $p = str_replace('\\', '/', $path);
        $views = str_replace('\\', '/', resource_path('views')).'/';
        if (! str_starts_with($p, $views)) {
            return false;
        }
        $rel = substr($p, strlen($views));

        return str_starts_with($rel, 'site/')
            || str_starts_with($rel, 'components/site/')
            || in_array($rel, ['layouts/document.blade.php', 'layouts/site.blade.php'], true);
    }

    public static function compact(string $source): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);

        return (string) preg_replace_callback(
            '/[ \t]*\n\s*/',
            static function (array $m) use ($source): string {
                [$ws, $offset] = $m[0];
                $prev = $offset > 0 ? $source[$offset - 1] : '';
                $nextPos = $offset + strlen($ws);
                $next = $nextPos < strlen($source) ? $source[$nextPos] : '';
                if ($prev === '' || $next === '') {
                    return '';
                }
                // Blade only sees a directive that does not follow a word character (`@endif@if` is
                // text), so a directive glued to a word gets an empty PHP tag as separator.
                if ($next === '@' && preg_match('/\w/', $prev) === 1) {
                    return '<?php ?>';
                }
                if ($prev === '>' || $prev === '}' || $next === '<' || $next === '{' || $next === '@') {
                    return '';
                }

                return ' ';
            },
            $source,
            -1,
            $count,
            PREG_OFFSET_CAPTURE,
        );
    }
}
