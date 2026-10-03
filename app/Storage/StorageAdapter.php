<?php

declare(strict_types=1);

namespace App\Storage;

/**
 * Port of sakila/lib/storage/types.ts. Local disk (cPanel; served by UploadsController) or
 * S3-compatible (R2/S3/MinIO). Binary files never go into the database (ADR-004).
 */
interface StorageAdapter
{
    /** "local" | "s3" */
    public function driver(): string;

    /** @return array{key: string, url: string, size: int} */
    public function put(string $key, string $body, string $contentType, ?string $cacheControl = null): array;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    public function publicUrl(string $key): string;

    /** Local adapter only: absolute path of a stored object, or null when missing. */
    public function path(string $key): ?string;
}
