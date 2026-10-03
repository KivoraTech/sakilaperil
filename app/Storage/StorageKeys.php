<?php

declare(strict_types=1);

namespace App\Storage;

/** Storage key rules (port of lib/storage/types.ts + validation.ts key helpers). */
final class StorageKeys
{
    /** Keys are generated (random) or "seed/…"; never user-controlled. */
    public const PATTERN = '/^[a-z0-9][a-z0-9\/_.-]*$/i';

    public static function isValid(string $key): bool
    {
        if ($key === '' || strlen($key) > 300 || preg_match(self::PATTERN, $key) !== 1) {
            return false;
        }
        foreach (explode('/', $key) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    /** Random, unguessable key: "2026/09/3f9c…a1.jpg". Never derived from the user's filename. */
    public static function generate(string $ext, ?\DateTimeInterface $now = null): string
    {
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $ext = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $ext));

        return $now->format('Y').'/'.$now->format('m').'/'.bin2hex(random_bytes(16)).'.'.$ext;
    }

    /** Display-only filename: strips paths/control chars, limits length. */
    public static function sanitizeFilename(string $name): string
    {
        $parts = preg_split('#[\\\\/]#', $name) ?: [];
        $base = (string) end($parts);
        $cleaned = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{M}\p{N}._ -]/u', '', $base)));

        return mb_substr($cleaned !== '' ? $cleaned : 'file', 0, 120);
    }
}
