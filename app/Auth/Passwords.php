<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Password hashing (port of sakila/lib/auth/password.ts): argon2id with OWASP parameters
 * (19 MiB, t=2, p=1) when PHP was built with argon2, otherwise bcrypt (cost 12) — many shared
 * hosts ship PHP without argon2. Hashes from the reference app ($argon2id$…) still verify via
 * libsodium when PHP lacks native argon2. Outdated hashes are upgraded on the next login.
 */
final class Passwords
{
    public const MIN_LENGTH = 12;

    public const MAX_LENGTH = 128;

    /**
     * Why a password of acceptable length is still too weak, or null. Length is the main defence
     * (12+); this only stops the trivial ones: one or two repeated characters, plain sequences and
     * the handful of 12+ character passwords everyone tries first.
     */
    public static function weakness(string $password): ?string
    {
        $lower = mb_strtolower($password);
        if (count(array_unique(mb_str_split($lower))) < 5) {
            return 'This password is too simple: use more different characters.';
        }
        $compact = preg_replace('/[\s\-_.!@#]+/u', '', $lower) ?? $lower;
        foreach (['password', 'qwerty', '123456', 'abcdef', 'letmein', 'changeme', 'sakila', 'admin', 'welcome', 'iloveyou'] as $common) {
            // e.g. "passwordpassword", "Password1234", "sakila-sakila-1": a common word plus padding.
            $rest = str_replace($common, '', $compact);
            if ($rest !== $compact && preg_match('/^[0-9]*$/', $rest) === 1) {
                return 'This password is too easy to guess.';
            }
        }
        foreach (['0123456789012345678901234567890', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiopasdfghjklzxcvbnm'] as $sequence) {
            if (str_contains($sequence, $compact) || str_contains(strrev($sequence), $compact)) {
                return 'This password is too easy to guess.';
            }
        }

        return null;
    }

    public static function hash(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, self::argonOptions());
        }

        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verify(?string $hash, string $password): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }
        if (str_starts_with($hash, '$argon2') && ! defined('PASSWORD_ARGON2ID')) {
            if (function_exists('sodium_crypto_pwhash_str_verify')) {
                try {
                    return sodium_crypto_pwhash_str_verify($hash, $password);
                } catch (\Throwable) {
                    return false;
                }
            }

            return false;
        }

        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::argonOptions());
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /** @return array{memory_cost:int, time_cost:int, threads:int} */
    private static function argonOptions(): array
    {
        return ['memory_cost' => 19456, 'time_cost' => 2, 'threads' => 1];
    }
}
