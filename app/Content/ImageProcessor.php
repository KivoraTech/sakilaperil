<?php

declare(strict_types=1);

namespace App\Content;

use GdImage;
use RuntimeException;

/**
 * Server-side processing of uploaded images (port of sakila/lib/storage/image.ts, sharp → GD):
 * - auto-orients from EXIF and STRIPS all metadata (EXIF/GPS: photos of beneficiaries must not
 *   leak locations) by re-encoding the pixels,
 * - caps the master at MAX_EDGE px on the longest side (same format, same qualities as the reference),
 * - returns the real width/height for layout (no CLS).
 * Animated GIFs are kept byte-for-byte (dimensions only). A file that sniffs as an image but cannot
 * be decoded is rejected.
 *
 * Shared hosts often build GD without WebP/AVIF. Then the bytes are kept (not resized), WebP
 * metadata chunks (EXIF/XMP) are removed losslessly, and dimensions come from the file header.
 */
final class ImageProcessor
{
    public const MAX_EDGE = 2560;

    /** Decoding cost guard (RGBA bitmap in PHP memory): 50 MP ≈ 200 MB. */
    public const MAX_PIXELS = 50_000_000;

    /**
     * @return array{bytes: string, width: int, height: int}
     *
     * @throws RuntimeException when the image cannot be read
     */
    public static function process(string $bytes, string $mimeType): array
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new RuntimeException('Unreadable image');
        }
        [$w, $h] = [(int) $info[0], (int) $info[1]];
        if ($w * $h > self::MAX_PIXELS) {
            throw new RuntimeException('Image too large in pixels');
        }

        if ($mimeType === 'image/gif') {
            return ['bytes' => $bytes, 'width' => $w, 'height' => $h];
        }
        if (! self::canDecode($mimeType)) {
            if ($mimeType === 'image/webp') {
                return ['bytes' => self::stripWebpMetadata($bytes), 'width' => $w, 'height' => $h];
            }
            if ($mimeType === 'image/avif') {
                return ['bytes' => $bytes, 'width' => $w, 'height' => $h];
            }
            throw new RuntimeException("Unsupported image type {$mimeType}");
        }

        $img = @imagecreatefromstring($bytes);
        if (! $img instanceof GdImage) {
            throw new RuntimeException('Unreadable image');
        }
        $img = self::orient($img, $mimeType === 'image/jpeg' ? self::exifOrientation($bytes) : 1);
        $img = self::fitInside($img, self::MAX_EDGE);

        $out = self::encode($img, $mimeType);
        $width = imagesx($img);
        $height = imagesy($img);
        imagedestroy($img);

        return ['bytes' => $out, 'width' => $width, 'height' => $height];
    }

    public static function canDecode(string $mimeType): bool
    {
        if (! function_exists('gd_info')) {
            return false;
        }
        $gd = gd_info();

        return match ($mimeType) {
            'image/jpeg' => (bool) ($gd['JPEG Support'] ?? false),
            'image/png' => (bool) ($gd['PNG Support'] ?? false),
            'image/webp' => (bool) ($gd['WebP Support'] ?? false) && function_exists('imagewebp'),
            'image/avif' => (bool) ($gd['AVIF Support'] ?? false) && function_exists('imageavif'),
            default => false,
        };
    }

    private static function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            return 1;
        }
        fwrite($stream, $bytes);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return $o >= 1 && $o <= 8 ? $o : 1;
    }

    /** Apply EXIF orientation (1–8) so the pixels are upright once the metadata is gone. */
    private static function orient(GdImage $img, int $orientation): GdImage
    {
        $rotate = function (GdImage $src, int $ccwDegrees): GdImage {
            $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
            $r = imagerotate($src, $ccwDegrees, $transparent === false ? 0 : $transparent);
            if (! $r instanceof GdImage) {
                return $src;
            }
            imagedestroy($src);

            return $r;
        };

        switch ($orientation) {
            case 2:
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $img = $rotate($img, 180);
                break;
            case 4:
                imageflip($img, IMG_FLIP_VERTICAL);
                break;
            case 5: // transpose: 90° clockwise + mirror
                $img = $rotate($img, 270);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 6: // 90° clockwise
                $img = $rotate($img, 270);
                break;
            case 7: // transverse: 90° counter-clockwise + mirror
                $img = $rotate($img, 90);
                imageflip($img, IMG_FLIP_HORIZONTAL);
                break;
            case 8: // 90° counter-clockwise
                $img = $rotate($img, 90);
                break;
        }

        return $img;
    }

    private static function fitInside(GdImage $img, int $maxEdge): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $maxEdge && $h <= $maxEdge) {
            return $img;
        }
        $scale = min($maxEdge / $w, $maxEdge / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if (! $dst instanceof GdImage) {
            throw new RuntimeException('Could not allocate the resized image');
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($dst, 0, 0, $transparent);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return $dst;
    }

    private static function encode(GdImage $img, string $mimeType): string
    {
        ob_start();
        try {
            $ok = match ($mimeType) {
                'image/jpeg' => (function () use ($img): bool {
                    imageinterlace($img, true); // progressive, like mozjpeg

                    return imagejpeg($img, null, 82);
                })(),
                'image/png' => (function () use ($img): bool {
                    imagealphablending($img, false);
                    imagesavealpha($img, true);

                    return imagepng($img, null, 9);
                })(),
                'image/webp' => imagewebp($img, null, 82),
                'image/avif' => imageavif($img, null, 60),
                default => false,
            };
            $data = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $ok || $data === '') {
            throw new RuntimeException('Could not encode the image');
        }

        return $data;
    }

    /**
     * Lossless removal of the EXIF and XMP chunks of a WebP (RIFF) file, clearing the matching
     * VP8X flags. Image data is untouched. Malformed files are returned unchanged.
     */
    public static function stripWebpMetadata(string $bytes): string
    {
        if (strlen($bytes) < 12 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            return $bytes;
        }
        $out = '';
        $pos = 12;
        $len = strlen($bytes);
        while ($pos + 8 <= $len) {
            $fourcc = substr($bytes, $pos, 4);
            $size = unpack('V', substr($bytes, $pos + 4, 4))[1] ?? 0;
            $padded = $size + ($size & 1);
            if ($pos + 8 + $size > $len) {
                return $bytes;
            }
            $chunk = substr($bytes, $pos, 8 + $padded);
            if ($fourcc === 'VP8X' && $size >= 10) {
                // Flags byte: bit 3 = EXIF, bit 2 = XMP.
                $flags = ord($chunk[8]) & ~(0x08 | 0x04);
                $chunk[8] = chr($flags);
            }
            if ($fourcc !== 'EXIF' && $fourcc !== 'XMP ') {
                $out .= $chunk;
            }
            $pos += 8 + $padded;
        }

        return 'RIFF'.pack('V', 4 + strlen($out)).'WEBP'.$out;
    }
}
