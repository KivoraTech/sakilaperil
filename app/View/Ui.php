<?php

declare(strict_types=1);

namespace App\View;

use App\Settings\SettingsTodo;
use Illuminate\Support\HtmlString;

/**
 * Ports of the small presentational helpers in components/site/ui/* (button-link, smart-link,
 * todo-text, todo, section surfaces, blocks/shared). Markup and class strings are identical.
 */
final class Ui
{
    /** lib/blocks/fields.ts SAFE_HREF: internal path, #anchor, https URL, mailto:, tel:. */
    public const SAFE_HREF = '/^(\/(?![\/\\\\])[^\s\\\\]*|#[A-Za-z][\w-]*|https:\/\/[^\s]+|mailto:[^\s]+|tel:\+?[\d\s()-]+)$/';

    private const BUTTON_BASE = 'group/btn relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-full font-semibold whitespace-normal text-center transition-[background-color,color,box-shadow,transform,translate] duration-300 ease-out-soft';

    /**
     * @var array<string, string>
     */
    private const BUTTON_VARIANTS = [
        'primary' => 'btn-fill bg-cta text-cta-fg shadow-cta [--btn-fill:var(--color-cta-hover)] active:translate-y-px motion:hover:-translate-y-0.5',
        'secondary' => 'btn-fill text-heading ring-[1.5px] ring-heading ring-inset [--btn-fill:var(--color-heading)] hover:text-bg motion:hover:-translate-y-0.5',
        'ghost-light' => 'btn-fill text-white ring-[1.5px] ring-white/80 ring-inset backdrop-blur-sm [--btn-fill:var(--color-white)] hover:text-deep motion:hover:-translate-y-0.5',
        'light' => 'btn-fill bg-white text-himalaya [--btn-fill:var(--color-cream)] motion:hover:-translate-y-0.5',
        'quiet' => 'rounded-none! px-0! min-h-0! text-accent-text underline decoration-1 underline-offset-[5px] hover:decoration-2',
    ];

    /**
     * @var array<string, string>
     */
    private const BUTTON_SIZES = [
        'sm' => 'min-h-11 px-5 text-small',
        'md' => 'min-h-12 px-6 text-body',
        'lg' => 'min-h-14 px-7 text-body',
    ];

    /**
     * @var array<string, string>
     */
    public const SURFACE_CLASS = [
        'paper' => 'bg-bg',
        'cream' => 'surface-cream',
        'white' => 'surface-white',
        'deep' => 'surface-dark',
        'himalaya' => 'surface-dark surface-himalaya',
        'marigold' => 'surface-marigold',
    ];

    public static function buttonClass(string $variant = 'primary', string $size = 'md', ?string $class = null): string
    {
        return Html::cx(self::BUTTON_BASE, self::BUTTON_SIZES[$size], self::BUTTON_VARIANTS[$variant], $class);
    }

    /** blocks/shared.ts surfaceOf(): block `settings.background` → section surface. */
    public static function surfaceOf(?string $background): string
    {
        return in_array($background, ['cream', 'white', 'himalaya', 'deep'], true) ? $background : 'paper';
    }

    public static function isDark(string $surface): bool
    {
        return $surface === 'deep' || $surface === 'himalaya';
    }

    public static function isExternal(string $href): bool
    {
        return preg_match('/^https?:\/\//', $href) === 1;
    }

    /**
     * smart-link.tsx: locale-less internal paths get the locale prefix, https opens in a new tab
     * with rel=noopener, #anchors/mailto/tel stay plain; anything unsafe renders as a <span>.
     *
     * @param  array<string, mixed>  $attrs  extra attributes (class, aria-*, style…), rendered first like {...rest}
     */
    public static function smartLink(string $href, string|HtmlString $inner, array $attrs = [], ?string $newTabLabel = null, ?string $locale = null): HtmlString
    {
        $content = $inner instanceof HtmlString ? $inner->toHtml() : e($inner);
        if (preg_match(self::SAFE_HREF, $href) !== 1) {
            return new HtmlString('<span'.Html::attrs(['class' => $attrs['class'] ?? null]).'>'.$content.'</span>');
        }
        if (str_starts_with($href, '/')) {
            return new HtmlString('<a'.Html::attrs([...$attrs, 'href' => lurl($href, $locale)]).'>'.$content.'</a>');
        }
        if (self::isExternal($href)) {
            $suffix = $newTabLabel ? '<span class="sr-only"> '.e($newTabLabel).'</span>' : '';

            return new HtmlString('<a'.Html::attrs(['href' => $href, 'target' => '_blank', 'rel' => 'noopener noreferrer', ...$attrs]).'>'.$content.$suffix.'</a>');
        }

        return new HtmlString('<a'.Html::attrs(['href' => $href, ...$attrs]).'>'.$content.'</a>');
    }

    /**
     * button-link.tsx ButtonLink.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function buttonLink(string $href, string|HtmlString $label, string $variant = 'primary', string $size = 'md', bool $arrow = false, ?string $class = null, ?string $newTabLabel = null, array $attrs = []): HtmlString
    {
        $inner = '<span>'.($label instanceof HtmlString ? $label->toHtml() : e($label)).'</span>';
        if ($arrow) {
            $inner .= Icon::svg('ArrowRight', 'size-[1.1em] shrink-0 transition-transform duration-300 motion:group-hover/btn:translate-x-1');
        }

        return self::smartLink($href, new HtmlString($inner), ['class' => self::buttonClass($variant, $size, $class), ...$attrs], $newTabLabel);
    }

    /**
     * todo-text.tsx: "[[TODO: …]]" placeholders inside plain CMS text become a dashed marker.
     */
    public static function todoText(?string $text): HtmlString
    {
        $text ??= '';
        if (! str_contains($text, '[[TODO')) {
            return new HtmlString(e($text));
        }
        $parts = preg_split('/(\[\[TODO:[^\]]*\]\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if (preg_match('/^\[\[TODO:\s*([^\]]*)\]\]$/', $part, $m) === 1) {
                $out .= '<span data-todo="true" class="mx-0.5 inline-block rounded-[6px] border border-dashed border-warning/60 bg-warning-bg px-1.5 py-0.5 align-baseline text-[0.85em] leading-snug font-medium text-warning">'.e($m[1]).'</span>';
            } else {
                $out .= e($part);
            }
        }

        return new HtmlString($out);
    }

    /**
     * todo.tsx Fact: known value → rendered (optionally through $render), TODO + demo mode →
     * "to confirm" chip, TODO in production → nothing.
     *
     * @param  (callable(string): (string|HtmlString))|null  $render
     */
    public static function fact(?string $value, bool $demo, string $label, ?callable $render = null): HtmlString
    {
        if ($value === null || $value === '') {
            return new HtmlString('');
        }
        if (! SettingsTodo::isTodo($value)) {
            if ($render === null) {
                return new HtmlString(e($value));
            }
            $r = $render($value);

            return $r instanceof HtmlString ? $r : new HtmlString(e($r));
        }
        if (! $demo) {
            return new HtmlString('');
        }

        return new HtmlString('<span title="'.e($value).'" class="inline-flex items-center rounded-[6px] border border-dashed border-current/50 px-2 py-0.5 text-[0.8125rem] font-medium opacity-90">'.e($label).'</span>');
    }

    /**
     * next-intl Link with `{ pathname, query }`: "/donate" + ["amount" => 25] → "/it/donate?amount=25".
     *
     * @param  array<string, mixed>  $query
     */
    public static function href(string $pathname, array $query = [], ?string $locale = null): string
    {
        $query = array_filter($query, static fn ($v): bool => $v !== null && $v !== '');
        $qs = $query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return lurl($pathname, $locale).$qs;
    }

    /**
     * Columns for a list of cards. Grids were designed for three or more cards; with one or two
     * they would leave half the row empty, so fewer cards get fewer, wider columns.
     */
    public static function cardGrid(int $count): string
    {
        return match (true) {
            $count <= 1 => 'max-w-2xl',
            $count === 2 => 'sm:grid-cols-2 lg:max-w-5xl',
            default => 'sm:grid-cols-2 lg:grid-cols-3',
        };
    }
}
