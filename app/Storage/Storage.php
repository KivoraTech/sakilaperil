<?php

declare(strict_types=1);

namespace App\Storage;

use RuntimeException;

/**
 * STORAGE_DRIVER=local (default; dev/test and cPanel production with LOCAL_UPLOADS_DIR)
 * | s3 (R2/S3/MinIO, env S3_*). Misconfiguration fails loudly.
 */
final class Storage
{
    private static ?StorageAdapter $adapter = null;

    public static function adapter(): StorageAdapter
    {
        if (self::$adapter !== null) {
            return self::$adapter;
        }
        $driver = trim((string) config('sakila.storage_driver')) ?: 'local';

        return self::$adapter = match ($driver) {
            'local' => new LocalStorage,
            's3' => new S3Storage(S3Storage::configFromEnv()),
            default => throw new RuntimeException("Unknown STORAGE_DRIVER \"{$driver}\""),
        };
    }

    /** Adapter able to delete a given asset's object (by the driver it was stored with). */
    public static function for(string $driver): ?StorageAdapter
    {
        if ($driver === 'external') {
            return null; // seeded placeholder URLs: nothing to delete
        }
        $current = self::adapter();
        if ($current->driver() === $driver) {
            return $current;
        }

        return $driver === 'local' ? new LocalStorage : null;
    }

    /** Tests only. */
    public static function reset(): void
    {
        self::$adapter = null;
    }
}
