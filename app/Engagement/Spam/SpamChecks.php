<?php

declare(strict_types=1);

namespace App\Engagement\Spam;

/**
 * Pure anti-spam checks (port of lib/spam/checks.ts; ADR-008). Layers: honeypot + minimum fill time
 * (here), a database fixed-window rate limit (RateLimiter) and optional Cloudflare Turnstile.
 * The time trap is a weak signal on its own (the timestamp comes from the browser).
 */
final class SpamChecks
{
    /** Name of the hidden honeypot input. Deliberately not an autofill-able name. */
    public const HONEYPOT_FIELD = 'hp_extra';

    /** Minimum time a human needs to fill a form (ms). */
    public const MIN_FILL_MS = 2500;

    /** A form open longer than this is treated as stale/replayed (ms). */
    public const MAX_FILL_MS = 86_400_000;

    /** Per-form limits, per hashed IP. Generous enough for a family sharing one connection. */
    public const RATE_LIMITS = [
        'contact' => ['limit' => 5, 'windowSec' => 600],
        'volunteer' => ['limit' => 3, 'windowSec' => 600],
        'membership' => ['limit' => 3, 'windowSec' => 600],
        'partner' => ['limit' => 3, 'windowSec' => 600],
        'newsletter' => ['limit' => 5, 'windowSec' => 600],
        'donation' => ['limit' => 10, 'windowSec' => 600],
    ];

    /**
     * @return array{ok: true}|array{ok: false, reason: 'honeypot'|'too_fast'|'stale'|'bad_timestamp'}
     */
    public static function honeypotAndTiming(mixed $honeypot, mixed $startedAt, ?int $nowMs = null, int $minMs = self::MIN_FILL_MS, int $maxMs = self::MAX_FILL_MS): array
    {
        $now = $nowMs ?? (int) floor(microtime(true) * 1000);
        if (is_string($honeypot) && trim($honeypot) !== '') {
            return ['ok' => false, 'reason' => 'honeypot'];
        }
        if ($honeypot !== null && ! is_string($honeypot)) {
            return ['ok' => false, 'reason' => 'honeypot'];
        }
        $started = is_int($startedAt) || is_float($startedAt) ? $startedAt : (is_numeric($startedAt) ? (float) $startedAt : NAN);
        if (! is_finite((float) $started) || $started <= 0) {
            return ['ok' => false, 'reason' => 'bad_timestamp'];
        }
        $elapsed = $now - $started;
        if ($elapsed < $minMs) {
            return ['ok' => false, 'reason' => 'too_fast'];
        }
        if ($elapsed > $maxMs) {
            return ['ok' => false, 'reason' => 'stale'];
        }

        return ['ok' => true];
    }

    /**
     * Fixed-window transition (mirrors the SQL in RateLimiter; reference implementation for tests).
     *
     * @param  array{count: int, windowStart: int}|null  $current  windowStart in ms
     * @return array{count: int, windowStart: int}
     */
    public static function nextWindow(?array $current, int $nowMs, int $windowMs): array
    {
        if ($current === null || $nowMs - $current['windowStart'] >= $windowMs) {
            return ['count' => 1, 'windowStart' => $nowMs];
        }

        return ['count' => $current['count'] + 1, 'windowStart' => $current['windowStart']];
    }
}
