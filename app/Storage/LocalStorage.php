<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

/**
 * Local-disk adapter (port of lib/storage/local.ts). Files live in LOCAL_UPLOADS_DIR and are
 * served by UploadsController at /uploads/{key} (stored MIME, nosniff, ETag). In production the
 * folder is REQUIRED and must be an absolute path OUTSIDE the app (e.g. /home/<user>/sakila-uploads)
 * so deploying a new version never deletes photos.
 */
final class LocalStorage implements StorageAdapter
{
    private string $root;

    public function __construct(?string $root = null, private readonly string $baseUrl = '/uploads')
    {
        $this->root = rtrim($root ?? self::uploadsDir(), '/');
    }

    public static function uploadsDir(): string
    {
        $configured = trim((string) config('sakila.local_uploads_dir'));
        if (app()->environment('production') && ! app()->runningUnitTests()) {
            if ($configured === '') {
                throw new RuntimeException('LOCAL_UPLOADS_DIR is required in production with STORAGE_DRIVER=local (an absolute folder outside the app, e.g. /home/<user>/sakila-uploads).');
            }
            if (! str_starts_with($configured, '/')) {
                throw new RuntimeException("LOCAL_UPLOADS_DIR must be an absolute path, got \"{$configured}\"");
            }
        }

        return $configured !== '' ? $configured : storage_path('uploads');
    }

    public function driver(): string
    {
        return 'local';
    }

    public function put(string $key, string $body, string $contentType, ?string $cacheControl = null): array
    {
        $file = $this->resolve($key);
        if (! is_dir(dirname($file)) && ! mkdir(dirname($file), 0755, true) && ! is_dir(dirname($file))) {
            throw new RuntimeException('Could not create the uploads folder');
        }
        if (file_put_contents($file, $body, LOCK_EX) === false) {
            throw new RuntimeException('Could not write the uploaded file');
        }

        return ['key' => $key, 'url' => $this->publicUrl($key), 'size' => strlen($body)];
    }

    public function delete(string $key): void
    {
        $file = $this->resolve($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function exists(string $key): bool
    {
        return is_file($this->resolve($key));
    }

    public function path(string $key): ?string
    {
        $file = $this->resolve($key);

        return is_file($file) ? $file : null;
    }

    public function publicUrl(string $key): string
    {
        return $this->baseUrl.'/'.$key;
    }

    private function resolve(string $key): string
    {
        if (! StorageKeys::isValid($key)) {
            throw new RuntimeException('Invalid storage key');
        }

        return $this->root.'/'.$key;
    }
}
