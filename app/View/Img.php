<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\HtmlString;

/**
 * next/image equivalent (same markup as the reference's `<Image>`): responsive `srcset` built with
 * Next's width rules, served by ImageController (`/_img?url=…&w=…&q=…`, WebP, disk-cached).
 *
 * Config mirrors the reference next.config.ts `images`: deviceSizes [640, 828, 1080, 1440, 1920],
 * imageSizes [64, 128, 256, 384], qualities [70, 75, 80].
 */
final class Img
{
    public const DEVICE_SIZES = [640, 828, 1080, 1440, 1920];

    public const IMAGE_SIZES = [64, 128, 256, 384];

    public const QUALITIES = [70, 75, 80];

    public const DEFAULT_QUALITY = 75;

    public const ENDPOINT = '/_img';

    /**
     * Priority images rendered during this request (layout emits the preload links).
     *
     * @var array<string, array{srcset: string, sizes: string}>
     */
    private static array $preloads = [];

    /**
     * @return list<int>
     */
    public static function allSizes(): array
    {
        $all = [...self::IMAGE_SIZES, ...self::DEVICE_SIZES];
        sort($all);

        return $all;
    }

    /**
     * components/site/ui/media-image.tsx: focal point → object-position, sand placeholder when the
     * asset is missing, `fill` covers the parent box.
     *
     * @param  array{url: string, width: ?int, height: ?int, alt: string, focalX?: ?float, focalY?: ?float}|null  $asset
     * @param  bool  $containPortrait  show portrait images whole (object-contain) instead of cropping them
     */
    public static function media(
        ?array $asset,
        string $sizes,
        ?string $alt = null,
        bool $priority = false,
        string $class = '',
        bool $fill = true,
        ?int $quality = null,
        bool $containPortrait = false,
    ): HtmlString {
        if ($asset === null) {
            return new HtmlString('<div aria-hidden="true" class="'.e(Html::cx('bg-sand', $fill ? 'absolute inset-0' : null, $class)).'"></div>');
        }
        $objectPosition = isset($asset['focalX'], $asset['focalY'])
            ? (int) round($asset['focalX'] * 100).'% '.(int) round($asset['focalY'] * 100).'%'
            : null;
        $altText = $alt ?? $asset['alt'];
        if ($fill || ! $asset['width'] || ! $asset['height']) {
            return self::image(
                src: $asset['url'],
                alt: $altText,
                sizes: $sizes,
                fill: true,
                priority: $priority,
                quality: $quality,
                // A portrait image (typically a poster: an image of text) in a landscape cover box is
                // shown whole on the box's neutral background instead of being cropped to a strip.
                class: Html::cx($containPortrait && self::isPortrait($asset) ? 'object-contain' : 'object-cover', $class),
                objectPosition: $objectPosition,
            );
        }

        return self::image(
            src: $asset['url'],
            alt: $altText,
            sizes: $sizes,
            width: $asset['width'],
            height: $asset['height'],
            priority: $priority,
            quality: $quality,
            class: Html::cx('h-auto w-full', $class),
            objectPosition: $objectPosition,
        );
    }

    /** @param  array<string, mixed>  $asset */
    public static function isPortrait(array $asset): bool
    {
        return ! empty($asset['width']) && ! empty($asset['height']) && (int) $asset['height'] > (int) $asset['width'] * 1.1;
    }

    /** The raw `<Image>` (gallery tiles use it directly). */
    public static function image(
        string $src,
        string $alt,
        string $sizes,
        bool $fill = false,
        ?int $width = null,
        ?int $height = null,
        bool $priority = false,
        ?int $quality = null,
        string $class = '',
        ?string $objectPosition = null,
        bool $eager = false,
    ): HtmlString {
        $q = $quality ?? self::DEFAULT_QUALITY;
        $unoptimized = self::isUnoptimized($src);
        $style = $fill
            ? ['position' => 'absolute', 'height' => '100%', 'width' => '100%', 'left' => '0', 'top' => '0', 'right' => '0', 'bottom' => '0', 'color' => 'transparent']
            : ['color' => 'transparent'];
        if ($objectPosition !== null) {
            $style['object-position'] = $objectPosition;
        }
        $srcSet = null;
        $finalSrc = $src;
        if (! $unoptimized) {
            $widths = self::widths($sizes);
            $srcSet = implode(', ', array_map(fn (int $w): string => self::url($src, $w, $q).' '.$w.'w', $widths));
            $finalSrc = self::url($src, $widths[count($widths) - 1], $q);
        }
        if ($priority && $srcSet !== null) {
            self::$preloads[$srcSet] = ['srcset' => $srcSet, 'sizes' => $sizes];
        }

        return new HtmlString('<img'.Html::attrs([
            'alt' => $alt,
            'loading' => $priority ? null : ($eager ? 'eager' : 'lazy'),
            'width' => $fill ? null : $width,
            'height' => $fill ? null : $height,
            'decoding' => 'async',
            'data-nimg' => $fill ? 'fill' : '1',
            'class' => $class === '' ? null : $class,
            'style' => $style,
            'sizes' => $unoptimized ? null : $sizes,
            'srcset' => $srcSet,
            'src' => $finalSrc,
        ]).'>');
    }

    /**
     * Next's getWidths(): with `vw` sizes, every configured size ≥ deviceSizes[0] × smallest ratio.
     *
     * @return list<int>
     */
    public static function widths(string $sizes): array
    {
        $all = self::allSizes();
        if (preg_match_all('/(^|\s)(1?\d?\d)vw/', $sizes, $m) > 0) {
            $ratio = min(array_map('intval', $m[2])) * 0.01;

            return array_values(array_filter($all, fn (int $s): bool => $s >= self::DEVICE_SIZES[0] * $ratio));
        }

        return $all;
    }

    public static function url(string $src, int $width, int $quality): string
    {
        return self::ENDPOINT.'?url='.rawurlencode($src).'&w='.$width.'&q='.$quality;
    }

    /** SVGs (and data/blob URLs) are served as-is, like next/image does without dangerouslyAllowSVG. */
    public static function isUnoptimized(string $src): bool
    {
        $path = strtolower((string) parse_url($src, PHP_URL_PATH));

        return str_ends_with($path, '.svg') || str_starts_with($src, 'data:') || str_starts_with($src, 'blob:');
    }

    /** `<link rel="preload" as="image">` for the priority images of this request. */
    public static function preloadLinks(): HtmlString
    {
        $out = '';
        foreach (self::$preloads as $p) {
            $out .= '<link rel="preload" as="image" imagesrcset="'.e($p['srcset']).'" imagesizes="'.e($p['sizes']).'">';
        }

        return new HtmlString($out);
    }

    public static function resetPreloads(): void
    {
        self::$preloads = [];
    }
}
