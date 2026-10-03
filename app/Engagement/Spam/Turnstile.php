<?php

declare(strict_types=1);

namespace App\Engagement\Spam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Optional Cloudflare Turnstile (port of lib/spam/turnstile.ts): active only when BOTH keys are set. */
final class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function isConfigured(): bool
    {
        return config('engagement.turnstile.secret_key') !== '' && config('engagement.turnstile.secret_key') !== null
            && config('engagement.turnstile.site_key') !== '' && config('engagement.turnstile.site_key') !== null;
    }

    public static function siteKey(): ?string
    {
        return self::isConfigured() ? (string) config('engagement.turnstile.site_key') : null;
    }

    /** True when Turnstile is not configured; false on network errors (fail closed only when opted in). */
    public static function verify(mixed $token, ?string $ip): bool
    {
        if (! self::isConfigured()) {
            return true;
        }
        if (! is_string($token) || strlen($token) < 10 || strlen($token) > 4096) {
            return false;
        }
        try {
            $body = ['secret' => (string) config('engagement.turnstile.secret_key'), 'response' => $token];
            if ($ip !== null) {
                $body['remoteip'] = $ip;
            }
            $res = Http::asForm()->timeout(8)->post(self::VERIFY_URL, $body);

            return $res->json('success') === true;
        } catch (Throwable $e) {
            Log::error('[spam] turnstile verification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
