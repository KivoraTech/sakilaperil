<?php

declare(strict_types=1);

namespace App\Engagement\Newsletter;

/**
 * Stateless, signed newsletter tokens (port of lib/newsletter/tokens.ts):
 * token = base64url(email) "." base64url(HMAC-SHA256(secret, "newsletter:<purpose>:<email>")).
 * Unsubscribe tokens never expire (the link in every email must keep working).
 *
 * Confirmation links are short-lived: createTimed()/verifyTimed() add the issue time to the signed
 * payload — base64url(email) "." base36(issuedAt) "." base64url(HMAC(secret, "newsletter:<purpose>:<email>:<issuedAt>")).
 */
final class Tokens
{
    public const PURPOSES = ['confirm', 'unsubscribe'];

    public static function create(string $secret, string $purpose, string $email): string
    {
        $normalized = strtolower(trim($email));

        return self::b64(($normalized)).'.'.self::sign($secret, $purpose, $normalized);
    }

    /** Returns the email when the token is valid for $purpose, otherwise null. */
    public static function verify(string $secret, string $purpose, mixed $token): ?string
    {
        if (! is_string($token) || strlen($token) > 1000) {
            return null;
        }
        $parts = explode('.', $token);
        $encoded = $parts[0];
        $mac = $parts[1] ?? '';
        if ($encoded === '' || $mac === '') {
            return null;
        }
        $email = base64_decode(strtr($encoded, '-_', '+/'), false);
        if (! str_contains($email, '@') || strlen($email) > 254 || ! mb_check_encoding($email, 'UTF-8')) {
            return null;
        }
        if (! hash_equals(self::sign($secret, $purpose, $email), $mac)) {
            return null;
        }

        return $email;
    }

    /** A token that verifyTimed() accepts for $maxAgeSec seconds after $issuedAt (unix seconds). */
    public static function createTimed(string $secret, string $purpose, string $email, ?int $issuedAt = null): string
    {
        $normalized = strtolower(trim($email));
        $ts = base_convert((string) ($issuedAt ?? time()), 10, 36);

        return self::b64($normalized).'.'.$ts.'.'.self::sign($secret, $purpose, $normalized.':'.$ts);
    }

    /** Returns the email when the timed token is valid for $purpose and not older than $maxAgeSec. */
    public static function verifyTimed(string $secret, string $purpose, mixed $token, int $maxAgeSec, ?int $now = null): ?string
    {
        if (! is_string($token) || strlen($token) > 1000) {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[2] === '' || preg_match('/^[0-9a-z]{1,12}$/', $parts[1]) !== 1) {
            return null;
        }
        $email = base64_decode(strtr($parts[0], '-_', '+/'), false);
        if (! str_contains($email, '@') || strlen($email) > 254 || ! mb_check_encoding($email, 'UTF-8')) {
            return null;
        }
        if (! hash_equals(self::sign($secret, $purpose, $email.':'.$parts[1]), $parts[2])) {
            return null;
        }
        $issuedAt = (int) base_convert($parts[1], 36, 10);
        $age = ($now ?? time()) - $issuedAt;

        // A small negative age tolerates clock differences; anything else in the future is invalid.
        return $age >= -300 && $age <= $maxAgeSec ? $email : null;
    }

    private static function sign(string $secret, string $purpose, string $email): string
    {
        return self::b64(hash_hmac('sha256', "newsletter:{$purpose}:{$email}", $secret, true));
    }

    private static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
