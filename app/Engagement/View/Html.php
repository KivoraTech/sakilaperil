<?php

declare(strict_types=1);

namespace App\Engagement\View;

/**
 * Markup helpers mirroring resources/js/site/engagement/{core,ui}.ts so the Blade output of the
 * engagement components equals the islands' (and the reference React components') markup.
 */
final class Html
{
    public const LIGHT_SCOPE = '[--color-fg:var(--color-ink)] [--color-fg-muted:var(--color-ink-soft)] [--color-heading:var(--color-himalaya)] '
        .'[--color-focus:var(--color-himalaya)] [--color-border-input:var(--color-line-strong)]';

    public const INPUT = 'block min-h-12 w-full rounded-[10px] border-[1.5px] border-border-input bg-white px-3.5 py-2.5 text-base text-ink '
        .'placeholder:text-ink-soft/70 transition-colors '
        .'focus-visible:border-himalaya focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus '
        .'aria-invalid:border-error disabled:cursor-not-allowed disabled:opacity-60';

    /** @var array<string, string>|null */
    private static ?array $icons = null;

    /** Escape like React (& < > " ' → &amp; &lt; &gt; &quot; &#x27;). */
    public static function e(mixed $value): string
    {
        return str_replace('&#039;', '&#x27;', htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
    }

    /** @param  array<string, string|int|float|bool|null>  $map  false/null omitted, true → name="" */
    public static function attrs(array $map): string
    {
        $out = '';
        foreach ($map as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $out .= $value === true ? " {$name}=\"\"" : " {$name}=\"".self::e($value).'"';
        }

        return $out;
    }

    /**
     * Join classes like `cn` (clsx + tailwind-merge) for the utilities these components combine:
     * drops falsy parts and keeps the LAST class of each conflict group (cursor, background colour,
     * opacity, outline colour, border style), plus exact duplicates. Verified against `cn` in
     * tests/Unit/Engagement/HtmlTest.php.
     */
    public static function cx(string|false|null ...$parts): string
    {
        $tokens = [];
        foreach ($parts as $part) {
            if (! is_string($part) || $part === '') {
                continue;
            }
            foreach (preg_split('/\s+/', trim($part)) ?: [] as $token) {
                if ($token !== '') {
                    $tokens[] = $token;
                }
            }
        }
        $seen = [];
        $out = [];
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            $key = self::conflictKey($tokens[$i]);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                array_unshift($out, $tokens[$i]);
            }
        }

        return implode(' ', $out);
    }

    /** "variants:group" for conflicting utilities, else the class itself. */
    private static function conflictKey(string $class): string
    {
        $pos = strrpos($class, ':');
        $variants = $pos === false ? '' : substr($class, 0, $pos + 1);
        $util = $pos === false ? $class : substr($class, $pos + 1);
        $group = match (true) {
            str_starts_with($util, 'cursor-') => 'cursor',
            preg_match('/^opacity-(\d+|\[.+\])$/', $util) === 1 => 'opacity',
            preg_match('/^bg-(white|black|transparent|current|paper|cream|\[#[0-9a-fA-F]+\]|[a-z]+-\d+(\/\d+)?|[a-z]+(-[a-z]+)?(\/\d+)?)$/', $util) === 1
                && ! preg_match('/^bg-(clip|origin|repeat|no-repeat|fixed|local|scroll|cover|contain|auto|center|top|bottom|left|right|none|gradient|blend|linear|radial|conic)/', $util) => 'bg-color',
            preg_match('/^outline-(\d+|offset-.+|none|dashed|dotted|double|solid|hidden)$/', $util) === 1 => $util === 'outline' ? 'outline' : (str_starts_with($util, 'outline-offset-') ? 'outline-offset' : (preg_match('/^outline-\d+$/', $util) ? 'outline-w' : 'outline-style')),
            str_starts_with($util, 'outline-') => 'outline-color',
            in_array($util, ['border-solid', 'border-dashed', 'border-dotted', 'border-double', 'border-none', 'border-hidden'], true) => 'border-style',
            default => null,
        };

        return $group === null ? $class : $variants.'#'.$group;
    }

    /** Lucide icon markup identical to lucide-react (resources/js/site/engagement/icons.json). */
    public static function icon(string $name, string $class): string
    {
        self::$icons ??= json_decode((string) file_get_contents(resource_path('js/site/engagement/icons.json')), true, 4, JSON_THROW_ON_ERROR);

        return str_replace('__CLASS__', self::e($class), self::$icons[$name] ?? '');
    }

    /**
     * <Field>: label (+ optional marker), control, hint, error.
     *
     * @param  callable(array{id: string, describedBy: ?string, invalid: bool}): string  $control
     */
    public static function field(string $id, string $label, callable $control, ?string $error = null, ?string $hint = null, ?string $optionalLabel = null, ?string $className = null): string
    {
        $hintId = $hint ? "{$id}-hint" : null;
        $errorId = $error ? "{$id}-error" : null;
        $describedBy = implode(' ', array_filter([$hintId, $errorId])) ?: null;

        return '<div class="'.self::e(self::cx('flex flex-col gap-1.5', $className)).'">'
            .'<label'.self::attrs(['for' => $id, 'class' => 'text-[15px] font-medium text-fg']).'>'.self::e($label)
            .($optionalLabel ? '<span class="ml-1 font-normal text-fg-muted">'.self::e($optionalLabel).'</span>' : '')
            .'</label>'
            .$control(['id' => $id, 'describedBy' => $describedBy, 'invalid' => (bool) $error])
            .($hint ? '<p'.self::attrs(['id' => $hintId, 'class' => 'text-sm text-fg-muted']).'>'.self::e($hint).'</p>' : '')
            .($error ? '<p'.self::attrs(['id' => $errorId, 'class' => 'flex items-start gap-1.5 text-sm font-medium text-error']).'>'
                .self::icon('AlertCircle', 'mt-0.5 size-4 shrink-0').self::e($error).'</p>' : '')
            .'</div>';
    }

    public static function checkbox(string $id, string $labelHtml, ?string $name = null, bool $checked = false, bool $ariaRequired = false, ?string $role = null): string
    {
        return '<div class="flex flex-col gap-1"><div class="flex items-start gap-3">'
            .'<input'.self::attrs([
                'id' => $id,
                'type' => 'checkbox',
                'data-role' => $role,
                'class' => 'mt-0.5 size-5 shrink-0 cursor-pointer rounded border-[1.5px] border-border-input accent-sindoor focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus',
                'aria-required' => $ariaRequired ? 'true' : null,
                'checked' => $checked,
                'name' => $name,
            ]).'/>'
            .'<label'.self::attrs(['for' => $id, 'class' => 'cursor-pointer text-[15px] leading-snug text-fg']).'>'.$labelHtml.'</label>'
            .'</div></div>';
    }

    public static function honeypot(string $label): string
    {
        return '<div aria-hidden="true" class="absolute -left-[10000px] h-px w-px overflow-hidden"><label>'.self::e($label)
            .'<input'.self::attrs(['type' => 'text', 'tabindex' => '-1', 'autocomplete' => 'off', 'name' => 'hp_extra', 'value' => '']).'/>'
            .'</label></div>';
    }

    public static function submitButton(string $label): string
    {
        return '<button'.self::attrs([
            'type' => 'submit',
            'aria-disabled' => 'false',
            'class' => 'inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-sindoor px-7 py-3 text-base font-semibold text-white shadow-[0_8px_20px_-8px_rgba(181,69,47,0.6)] transition-colors hover:bg-sindoor-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-wait disabled:opacity-70',
        ]).'>'.self::e($label).'</button>';
    }

    public static function turnstileSlot(?string $siteKey): string
    {
        return $siteKey ? '<div data-turnstile-slot="" class="min-h-[65px]"></div>' : '';
    }

    public static function privacyConsentLabel(string $before, string $link, string $after, string $href): string
    {
        return self::e($before)
            .'<a'.self::attrs(['href' => $href, 'target' => '_blank', 'rel' => 'noopener', 'class' => 'font-medium text-himalaya underline underline-offset-2']).'>'
            .self::e($link).'</a>'.self::e($after);
    }

    /**
     * Island props: `<script type="application/json" data-engagement-props>` placed right before the root.
     *
     * @param  array<string, mixed>  $props
     */
    public static function props(string $island, array $props): string
    {
        $json = json_encode(['island' => $island, 'props' => $props], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);

        return '<script type="application/json" data-engagement-props="">'.$json.'</script>';
    }
}
