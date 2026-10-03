<?php

declare(strict_types=1);

namespace App\Engagement\Spam;

use App\Support\RequestInfo;
use Illuminate\Support\Facades\Log;

/**
 * Every anti-spam layer for a public submission, cheapest first (port of lib/spam/index.ts):
 * rate limit (counts every attempt, including spam rejections) → honeypot/time trap → Turnstile.
 * Controllers refund the attempt (RateLimiter::refund) when the request then fails field validation.
 */
final class Guard
{
    /**
     * @param  array<string, mixed>  $meta  hp_extra, startedAt, turnstileToken
     * @return array{ok: true, ipHash: string}|array{ok: false, status: 400|403|429, code: 'SPAM'|'RATE_LIMITED'|'CAPTCHA', retryAfterSec?: int}
     */
    public static function check(string $action, array $meta): array
    {
        $rl = RateLimiter::forRequest($action);
        if (! $rl['allowed']) {
            return ['ok' => false, 'status' => 429, 'code' => 'RATE_LIMITED', 'retryAfterSec' => $rl['retryAfterSec']];
        }
        $verdict = SpamChecks::honeypotAndTiming($meta['hp_extra'] ?? null, $meta['startedAt'] ?? null);
        if (! $verdict['ok']) {
            Log::info("[spam] rejected {$action} submission: {$verdict['reason']}");

            return ['ok' => false, 'status' => 400, 'code' => 'SPAM'];
        }
        if (! Turnstile::verify($meta['turnstileToken'] ?? null, RequestInfo::clientIp())) {
            return ['ok' => false, 'status' => 403, 'code' => 'CAPTCHA'];
        }

        return ['ok' => true, 'ipHash' => $rl['ipHash']];
    }
}
