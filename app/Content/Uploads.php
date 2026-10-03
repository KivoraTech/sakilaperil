<?php

declare(strict_types=1);

namespace App\Content;

use App\Storage\Storage;
use App\Storage\StorageKeys;
use App\Storage\UploadValidator;
use RuntimeException;
use Throwable;

/**
 * Port of sakila/lib/storage/upload.ts: validate (sniffed MIME + size) → process images (orient,
 * strip metadata, cap size) → write under a random key. The caller creates the MediaAsset row
 * (and deletes the stored object again if that fails).
 */
final class Uploads
{
    /**
     * @param  list<string>|null  $allowedKinds
     * @return array{ok: true, data: array{kind: string, storageDriver: string, storageKey: string, url: string, filename: string, mimeType: string, size: int, width: ?int, height: ?int}}|array{ok: false, error: string, message: string}
     */
    public static function store(string $bytes, string $originalName, ?array $allowedKinds = null): array
    {
        $check = UploadValidator::validate($bytes, $allowedKinds);
        if (! $check['ok']) {
            return $check;
        }

        $body = $bytes;
        $width = null;
        $height = null;
        if ($check['kind'] === 'IMAGE') {
            try {
                $img = ImageProcessor::process($bytes, $check['mimeType']);
            } catch (Throwable $e) {
                $tooBig = $e instanceof RuntimeException && $e->getMessage() === 'Image too large in pixels';

                return [
                    'ok' => false,
                    'error' => $tooBig ? 'TOO_LARGE' : 'CORRUPT',
                    'message' => $tooBig
                        ? 'The image is too large (more than 50 megapixels). Resize it and try again.'
                        : 'The image could not be read. Is the file damaged?',
                ];
            }
            $body = $img['bytes'];
            $width = $img['width'];
            $height = $img['height'];
        }

        $storage = Storage::adapter();
        $key = StorageKeys::generate($check['ext']);
        $stored = $storage->put($key, $body, $check['mimeType'], 'public, max-age=31536000, immutable');

        return [
            'ok' => true,
            'data' => [
                'kind' => $check['kind'],
                'storageDriver' => $storage->driver(),
                'storageKey' => $stored['key'],
                'url' => $stored['url'],
                'filename' => StorageKeys::sanitizeFilename($originalName),
                'mimeType' => $check['mimeType'],
                'size' => $stored['size'],
                'width' => $width,
                'height' => $height,
            ],
        ];
    }
}
