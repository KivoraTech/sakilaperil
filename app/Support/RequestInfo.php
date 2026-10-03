<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The visitor's address and browser for rate limits, throttles and the audit log.
 *
 * `Request::ip()` already applies the trusted-proxy rules (App\Http\Middleware\TrustProxies):
 * without a trusted proxy it is the connecting address; behind one it is the right-most
 * forwarded address that is not itself a trusted proxy. The left-most X-Forwarded-For value and
 * headers such as CF-Connecting-IP / X-Real-IP are never read directly: a client can send them.
 */
final class RequestInfo
{
    public static function clientIp(): ?string
    {
        return request()->ip() ?: null;
    }

    public static function userAgent(): ?string
    {
        $ua = request()->userAgent();

        return $ua !== null ? mb_substr($ua, 0, 400) : null;
    }
}
