<?php

declare(strict_types=1);

namespace App\Engagement\Spam;

use App\Support\RequestInfo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Atomic MySQL/MariaDB fixed-window counter in `form_rate_limits` (port of lib/spam/rate-limit.ts).
 * The upsert takes a row lock, so the read in the same transaction sees exactly this request's
 * increment and concurrent requests cannot both slip under the limit. Fails OPEN on database errors
 * (logged) so a DB hiccup never blocks a donor; the other spam layers still apply.
 * MySQL evaluates SET assignments left to right: `count` is computed before `windowStart` resets.
 * Times use UTC_TIMESTAMP(3) (dates are stored in UTC; NOW() follows the session time zone).
 */
final class RateLimiter
{
    /** Salted SHA-256 of an IP address (first 32 hex chars). Raw IPs are never stored. */
    public static function hashIp(?string $ip): string
    {
        $salt = (string) config('engagement.ip_hash_salt', 'sakila');

        return substr(hash('sha256', $salt.':'.($ip ?? 'unknown')), 0, 32);
    }

    /**
     * @param  array{limit: int, windowSec: int}  $policy
     * @return array{allowed: bool, count: int, limit: int, retryAfterSec: int}
     */
    public static function consume(string $key, array $policy): array
    {
        $window = (int) $policy['windowSec'];
        try {
            $row = DB::transaction(function () use ($key, $window) {
                DB::statement(
                    'INSERT INTO `form_rate_limits` (`key`, `count`, `windowStart`) VALUES (?, 1, UTC_TIMESTAMP(3))
                     ON DUPLICATE KEY UPDATE
                       `count` = IF(`windowStart` <= UTC_TIMESTAMP(3) - INTERVAL ? SECOND, 1, `count` + 1),
                       `windowStart` = IF(`windowStart` <= UTC_TIMESTAMP(3) - INTERVAL ? SECOND, UTC_TIMESTAMP(3), `windowStart`)',
                    [$key, $window, $window],
                );

                return DB::selectOne('SELECT `count`, `windowStart` FROM `form_rate_limits` WHERE `key` = ?', [$key]);
            });
            $count = (int) ($row->count ?? 1);
            $startedMs = isset($row->windowStart)
                ? CarbonImmutable::parse((string) $row->windowStart, 'UTC')->getTimestampMs()
                : (int) floor(microtime(true) * 1000);
            $nowMs = (int) floor(microtime(true) * 1000);
            $retry = max(1, (int) ceil(($startedMs + $window * 1000 - $nowMs) / 1000));

            return ['allowed' => $count <= $policy['limit'], 'count' => $count, 'limit' => $policy['limit'], 'retryAfterSec' => $retry];
        } catch (Throwable $e) {
            Log::error('[spam] rate limit check failed (failing open)', ['error' => $e->getMessage()]);

            return ['allowed' => true, 'count' => 0, 'limit' => $policy['limit'], 'retryAfterSec' => 0];
        }
    }

    /**
     * Rate-limit a public action for the current request's (hashed) IP.
     *
     * @return array{allowed: bool, count: int, limit: int, retryAfterSec: int, ipHash: string}
     */
    public static function forRequest(string $action): array
    {
        $ipHash = self::hashIp(RequestInfo::clientIp());

        return [...self::consume("{$action}:{$ipHash}", SpamChecks::RATE_LIMITS[$action]), 'ipHash' => $ipHash];
    }

    /**
     * Give one attempt back: used when a request passed the spam checks but failed field
     * validation, so a visitor correcting a form is not locked out by their own typos.
     * Honeypot, time-trap and CAPTCHA rejections are never refunded.
     */
    public static function refund(string $action, string $ipHash): void
    {
        try {
            DB::update('UPDATE `form_rate_limits` SET `count` = GREATEST(`count` - 1, 0) WHERE `key` = ?', ["{$action}:{$ipHash}"]);
        } catch (Throwable $e) {
            Log::error('[spam] rate limit refund failed', ['error' => $e->getMessage()]);
        }
    }

    /** Housekeeping: delete windows older than a day. */
    public static function prune(): int
    {
        try {
            return DB::table('form_rate_limits')->where('windowStart', '<', now('UTC')->subDay()->format('Y-m-d H:i:s.v'))->delete();
        } catch (Throwable) {
            return 0;
        }
    }
}
