<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Collision-resistant ids in the same shape as Prisma's `cuid()` (v1): 25 lowercase base-36
 * characters starting with "c". Ids created by the Next.js app and by Laravel are interchangeable.
 */
final class Cuid
{
    private static int $counter = 0;

    public static function make(): string
    {
        $time = self::pad(base_convert((string) (int) floor(microtime(true) * 1000), 10, 36), 8);
        self::$counter = (self::$counter + 1) % (36 ** 4);
        $count = self::pad(base_convert((string) self::$counter, 10, 36), 4);
        $fingerprint = self::pad(base_convert((string) (getmypid() ?: random_int(0, 36 ** 2)), 10, 36), 2)
            .self::pad(base_convert((string) crc32(gethostname() ?: 'host'), 10, 36), 2);
        $random = self::pad(base_convert((string) random_int(0, 36 ** 4 - 1), 10, 36), 4)
            .self::pad(base_convert((string) random_int(0, 36 ** 4 - 1), 10, 36), 4);

        return 'c'.$time.$count.$fingerprint.$random;
    }

    public static function isValid(string $value): bool
    {
        return (bool) preg_match('/^c[a-z0-9]{24}$/', $value);
    }

    private static function pad(string $value, int $size): string
    {
        return substr(str_pad($value, $size, '0', STR_PAD_LEFT), -$size);
    }
}
