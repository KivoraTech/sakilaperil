<?php

declare(strict_types=1);

namespace App\View;

/** Helpers for components/site/gallery/gallery-grid.tsx (server half; the lightbox is resources/js/site/gallery.ts). */
final class Gallery
{
    private const VIDEO_ID = '/^[A-Za-z0-9_-]{4,20}$/';

    /**
     * `${focalX * 100}% ${focalY * 100}%` (not rounded, unlike MediaImage).
     *
     * @param  array<string, mixed>  $img
     */
    public static function focal(array $img): ?string
    {
        if (! isset($img['focalX'], $img['focalY'])) {
            return null;
        }

        return Html::num($img['focalX'] * 100).'% '.Html::num($img['focalY'] * 100).'%';
    }

    /**
     * Everything the lightbox needs (already-built srcset for the 100vw image, translated labels),
     * escaped for an inline <script type="application/json">.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public static function lightboxJson(array $items, bool $syncUrl): string
    {
        $out = [];
        foreach ($items as $item) {
            if ($item['type'] === 'PHOTO') {
                $widths = Img::widths('100vw');
                $src = (string) $item['image']['url'];
                $unopt = Img::isUnoptimized($src);
                $out[] = [
                    'id' => $item['id'],
                    'type' => 'PHOTO',
                    'alt' => (string) $item['image']['alt'],
                    'caption' => (string) $item['caption'],
                    'src' => $unopt ? $src : Img::url($src, $widths[count($widths) - 1], Img::DEFAULT_QUALITY),
                    'srcset' => $unopt ? null : implode(', ', array_map(fn (int $w) => Img::url($src, $w, Img::DEFAULT_QUALITY).' '.$w.'w', $widths)),
                ];
            } else {
                $valid = preg_match(self::VIDEO_ID, (string) $item['videoId']) === 1;
                $out[] = [
                    'id' => $item['id'],
                    'type' => 'VIDEO',
                    'caption' => (string) $item['caption'],
                    'provider' => $item['provider'],
                    'embed' => ! $valid ? null : ($item['provider'] === 'YOUTUBE'
                        ? 'https://www.youtube-nocookie.com/embed/'.$item['videoId'].'?autoplay=1&rel=0'
                        : 'https://player.vimeo.com/video/'.$item['videoId'].'?autoplay=1&dnt=1'),
                ];
            }
        }
        $labels = [
            'close' => t('gallery.close'),
            'previous' => t('gallery.previous'),
            'next' => t('gallery.next'),
            'video' => t('gallery.video'),
            'playVideo' => t('gallery.playVideo'),
            'counter' => t('gallery.counter', ['current' => '{current}', 'total' => '{total}']),
            'videoConsentYoutube' => t('gallery.videoConsent', ['provider' => 'YouTube']),
            'videoConsentVimeo' => t('gallery.videoConsent', ['provider' => 'Vimeo']),
        ];
        $json = json_encode(['items' => $out, 'labels' => $labels, 'syncUrl' => $syncUrl], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);

        return $json;
    }
}
