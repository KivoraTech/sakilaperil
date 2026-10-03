<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Models\MediaAsset;
use App\Storage\Storage;
use App\Storage\StorageKeys;
use App\View\Img;
use GdImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * next/image optimizer equivalent: GET /_img?url=<src>&w=<width>&q=<quality>.
 *
 * - Only configured widths (Img::allSizes) and qualities (70/75/80), like next.config `images`.
 * - Sources: local uploads ("/uploads/<key>", one canonical spelling) or https images that are in the
 *   media library AND on an allow-listed host (Wikimedia Commons for the demo, the S3/R2 public host).
 *   No redirects, no arbitrary remote fetches. New variants are rate-limited per visitor.
 * - Never enlarges; fixes JPEG EXIF orientation; keeps PNG/WebP transparency.
 * - WebP when the browser accepts it and GD supports it (as next/image with formats: ["image/webp"]),
 *   otherwise JPEG (or PNG with transparency). SVG/GIF are passed through untouched.
 * - Results are cached on disk (config sakila.img_cache_dir) and served with a 30-day public cache, like
 *   minimumCacheTTL. Large sources are refused to protect shared-hosting memory.
 */
final class ImageController
{
    private const MAX_SOURCE_BYTES = 20 * 1024 * 1024;

    private const MAX_PIXELS = 40_000_000;

    private const CACHE_SECONDS = 60 * 60 * 24 * 30;

    /** New variants one visitor may trigger per minute (a first page view needs about 20). */
    private const MISSES_PER_MINUTE = 120;

    public function __invoke(Request $request): Response
    {
        $raw = $request->query('url', '');
        $width = (int) $request->query('w', '0');
        $quality = (int) $request->query('q', (string) Img::DEFAULT_QUALITY);
        $url = is_string($raw) ? $this->canonicalSource($raw) : null;
        if (! in_array($width, Img::allSizes(), true) || ! in_array($quality, Img::QUALITIES, true)) {
            return response('Invalid image request', 400);
        }
        if ($url === null) {
            return response('Image not found', 404);
        }
        $webp = function_exists('imagewebp') && str_contains((string) $request->header('Accept'), 'image/webp');
        // The key is built from the CANONICAL source, so one image has exactly one cache entry per
        // width, quality and format, however the URL was spelled.
        $key = sha1($url.'|'.$width.'|'.$quality.'|'.($webp ? 'webp' : 'legacy'));
        $dir = self::cacheDir().'/'.substr($key, 0, 2);
        foreach (['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'svg' => 'image/svg+xml'] as $ext => $mime) {
            $cached = "{$dir}/{$key}.{$ext}";
            if (is_file($cached)) {
                return $this->serve($request, $cached, $mime);
            }
        }

        // Only cache MISSES cost CPU and disk: limit them per visitor.
        $limiterKey = 'img-miss:'.sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($limiterKey, self::MISSES_PER_MINUTE)) {
            return response('Too many image requests', 429, ['Retry-After' => (string) RateLimiter::availableIn($limiterKey)]);
        }
        RateLimiter::hit($limiterKey, 60);

        $bytes = $this->load($url);
        if ($bytes === null) {
            return response('Image not found', 404);
        }
        $result = $this->transform($bytes, $width, $quality, $webp);
        if ($result === null) {
            return response('Unsupported image', 415);
        }
        [$out, $ext, $mime] = $result;
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = "{$dir}/{$key}.{$ext}";
        $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (@file_put_contents($tmp, $out) !== false && @rename($tmp, $file)) {
            return $this->serve($request, $file, $mime);
        }
        @unlink($tmp);

        return response($out, 200, [...$this->headers($mime), 'ETag' => '"'.sha1($out).'"']);
    }

    /** Folder of the resized variants (config `sakila.img_cache_dir`; pruned by sakila:housekeeping). */
    public static function cacheDir(): string
    {
        $dir = config('sakila.img_cache_dir');

        return is_string($dir) && $dir !== '' ? rtrim($dir, '/') : storage_path('app/img-cache');
    }

    /**
     * One spelling per source. Laravel has already URL-decoded the parameter once; anything still
     * percent-encoded, or not exactly an upload path / an image the CMS itself references, is refused.
     */
    private function canonicalSource(string $url): ?string
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\\\\]/', $url) === 1) {
            return null;
        }
        if (str_starts_with($url, '/uploads/')) {
            // Storage keys never contain "%": a still-encoded key is another spelling of the same file.
            $key = substr($url, strlen('/uploads/'));

            return StorageKeys::isValid($key) ? '/uploads/'.$key : null;
        }

        return $this->allowedRemote($url) ? $url : null;
    }

    private function load(string $url): ?string
    {
        if (str_starts_with($url, '/uploads/')) {
            try {
                $path = Storage::for('local')?->path(substr($url, strlen('/uploads/')));
            } catch (\Throwable) {
                return null;
            }
            if ($path === null || ! is_file($path) || filesize($path) > self::MAX_SOURCE_BYTES) {
                return null;
            }

            return (string) file_get_contents($path);
        }
        // Remote sources are downloaded ONCE and shared by every width (all variants of a page image
        // are requested at the same moment; fetching each separately trips the origin's rate limit).
        // A per-source lock makes concurrent requests wait for the first download.
        $dir = self::cacheDir().'/src';
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.'/'.sha1($url).'.bin';
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }
        $lock = @fopen($file.'.lock', 'c');
        if ($lock !== false) {
            flock($lock, LOCK_EX);
        }
        try {
            if (is_file($file)) {
                return (string) file_get_contents($file);
            }
            $body = $this->download($url);
            if ($body !== null) {
                $tmp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';
                if (@file_put_contents($tmp, $body) !== false) {
                    @rename($tmp, $file);
                }
            }

            return $body;
        } finally {
            if ($lock !== false) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** Streams the body and stops at MAX_SOURCE_BYTES (never buffers an oversized file in memory). */
    private function download(string $url): ?string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $res = Http::timeout(15)->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->withHeaders(['User-Agent' => 'SakilaPerIlNepal/1.0 (image optimizer; '.config('sakila.site_url').')'])
                    ->get($url);
                if ($res->status() === 429 || $res->serverError()) {
                    usleep((int) (300_000 * 2 ** $attempt));

                    continue;
                }
                if (! $res->successful() || (int) $res->header('Content-Length') > self::MAX_SOURCE_BYTES) {
                    return null;
                }
                $stream = $res->toPsrResponse()->getBody();
                $body = '';
                while (! $stream->eof()) {
                    $body .= $stream->read(65536);
                    if (strlen($body) > self::MAX_SOURCE_BYTES) {
                        $stream->close();

                        return null;
                    }
                }

                return $body;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Remote images are NOT proxied on request: the URL must be on an allow-listed https host
     * (Wikimedia Commons for the demo content, the configured S3/R2 public host) AND be the exact
     * address of a file in the media library. With self-hosted content nothing remote is fetched.
     */
    private function allowedRemote(string $url): bool
    {
        $u = parse_url($url);
        if (! is_array($u) || ($u['scheme'] ?? '') !== 'https' || empty($u['host']) || isset($u['user']) || isset($u['port'])) {
            return false;
        }
        $host = strtolower($u['host']);
        $path = (string) ($u['path'] ?? '/');
        $s3 = (string) config('sakila.s3.public_url');
        $hostOk = ($host === 'upload.wikimedia.org' && str_starts_with($path, '/wikipedia/commons/'))
            || ($s3 !== '' && $host === strtolower((string) parse_url($s3, PHP_URL_HOST)));
        if (! $hostOk) {
            return false;
        }
        try {
            return MediaAsset::query()->where('url', $url)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{0: string, 1: string, 2: string}|null [bytes, ext, mime] */
    private function transform(string $bytes, int $width, int $quality, bool $webp): ?array
    {
        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? (string) $info['mime'] : '';
        if ($mime === '' && str_contains(substr($bytes, 0, 1024), '<svg')) {
            return [$bytes, 'svg', 'image/svg+xml'];
        }
        if ($mime === 'image/gif') {
            return [$bytes, 'gif', 'image/gif'];
        }
        if (! is_array($info) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/avif'], true)) {
            return null;
        }
        $w0 = (int) $info[0];
        $h0 = (int) $info[1];
        if ($w0 < 1 || $h0 < 1 || $w0 * $h0 > self::MAX_PIXELS) {
            return null;
        }
        $src = @imagecreatefromstring($bytes);
        if (! $src instanceof GdImage) {
            return null;
        }
        if ($mime === 'image/jpeg') {
            $src = $this->orient($src, $bytes);
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $alpha = $mime !== 'image/jpeg';
        if ($width < $sw) {
            $th = max(1, (int) round($sh * $width / $sw));
            $dst = imagecreatetruecolor($width, $th);
            if ($alpha) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $th, $sw, $sh);
            $src = $dst;
        } elseif ($alpha) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }
        ob_start();
        if ($webp) {
            imagewebp($src, null, $quality);
            $ext = 'webp';
            $outMime = 'image/webp';
        } elseif ($alpha) {
            imagepng($src, null, 6);
            $ext = 'png';
            $outMime = 'image/png';
        } else {
            imageinterlace($src, true);
            imagejpeg($src, null, $quality);
            $ext = 'jpg';
            $outMime = 'image/jpeg';
        }
        $out = (string) ob_get_clean();

        return $out === '' ? null : [$out, $ext, $outMime];
    }

    /** Apply the EXIF orientation (sharp's .rotate() in next/image). */
    private function orient(GdImage $img, string $bytes): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $out = match ($o) {
            3 => imagerotate($img, 180, 0),
            6, 7 => imagerotate($img, -90, 0),
            5, 8 => imagerotate($img, 90, 0),
            default => $img,
        };
        if (! $out instanceof GdImage) {
            return $img;
        }
        if (in_array($o, [2, 4, 5, 7], true)) {
            imageflip($out, $o === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        }

        return $out;
    }

    private function serve(Request $request, string $file, string $mime): Response
    {
        $etag = '"'.sha1_file($file).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', must-revalidate']);
        }
        $response = new BinaryFileResponse($file, 200, $this->headers($mime));
        $response->headers->set('ETag', $etag);

        return $response;
    }

    /** @return array<string, string> */
    private function headers(string $mime): array
    {
        return [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', must-revalidate',
            'Vary' => 'Accept',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
            'Content-Security-Policy' => "script-src 'none'; frame-src 'none'; sandbox;",
        ];
    }
}
