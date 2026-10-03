<?php

declare(strict_types=1);

namespace App\View;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Media\Assets;
use Throwable;

/** Geometry for brand illustrations (port of components/site/brand/prayer-flags.tsx). */
final class Brand
{
    private const FLAG_COLORS = ['#2F6FB5', '#F5F2EA', '#C8372D', '#3C8D4E', '#F2C230'];

    private const FLAG_COUNT = 30;

    /** Bundled fallbacks, used until a logo / browser icon is chosen in Admin → Settings → Look & feel. */
    public const DEFAULT_LOGO = '/brand/logo-mark.png';

    public const DEFAULT_ICON = '/icon.png';

    /**
     * Logo and browser icon chosen in the settings (appearance.logoImageId / faviconImageId), so the
     * association can replace them from the admin without touching files. Never throws.
     *
     * @param  array<string, mixed>  $settings
     * @return array{logo: string, icon: string}
     */
    public static function images(array $settings): array
    {
        $appearance = is_array($settings['appearance'] ?? null) ? $settings['appearance'] : [];
        $logoId = is_string($appearance['logoImageId'] ?? null) ? $appearance['logoImageId'] : '';
        $iconId = is_string($appearance['faviconImageId'] ?? null) ? $appearance['faviconImageId'] : '';
        $ids = array_values(array_filter([$logoId, $iconId], fn (string $id): bool => $id !== ''));
        $assets = [];
        if ($ids !== []) {
            try {
                $assets = TagCache::remember([CacheTags::SETTINGS, CacheTags::MEDIA], 'brand-images:'.md5(implode(',', $ids)), fn (): array => Assets::byIds($ids));
            } catch (Throwable) {
                $assets = [];
            }
        }

        return [
            'logo' => $assets[$logoId]['url'] ?? self::DEFAULT_LOGO,
            'icon' => $assets[$iconId]['url'] ?? self::DEFAULT_ICON,
        ];
    }

    /** brand/social-icons.tsx socialNames. */
    public const SOCIAL_NAMES = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'x' => 'X', 'tiktok' => 'TikTok'];

    /**
     * The 30 garland flags along the rope `M0 30 Q360 120 720 60 T1440 90`.
     *
     * @return list<array{d: string, fill: string, delay: string, dur: string}>
     */
    public static function garlandFlags(): array
    {
        $flags = [];
        for ($i = 0; $i < self::FLAG_COUNT; $i++) {
            [$x, $y] = self::ropePoint(($i + 0.5) / self::FLAG_COUNT);
            $flags[] = [
                'd' => 'M'.self::toFixed($x - 16, 1).' '.self::toFixed($y, 1).' h32 l-2 40 h-28 z',
                'fill' => self::FLAG_COLORS[$i % count(self::FLAG_COLORS)],
                'delay' => self::toFixed(-fmod($i * 0.23, 3.2), 2).'s',
                'dur' => self::toFixed(2.6 + ($i % 4) * 0.35, 2).'s',
            ];
        }

        return $flags;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function ropePoint(float $u): array
    {
        [$p0, $c, $p1] = $u < 0.5 ? [[0, 30], [360, 120], [720, 60]] : [[720, 60], [1080, 0], [1440, 90]];
        $t = $u < 0.5 ? $u * 2 : ($u - 0.5) * 2;
        $k = fn (int $i): float => (1 - $t) ** 2 * $p0[$i] + 2 * (1 - $t) * $t * $c[$i] + $t ** 2 * $p1[$i];

        return [$k(0), $k(1)];
    }

    /** JS Number#toFixed (round half away from zero on the exact binary value; "-0.00" → "0.00"). */
    public static function toFixed(float $n, int $digits): string
    {
        $s = sprintf('%.'.$digits.'F', round($n, $digits, PHP_ROUND_HALF_UP));
        if (preg_match('/^-0\.?0*$/', $s) === 1) {
            return ltrim($s, '-');
        }

        return $s;
    }
}
