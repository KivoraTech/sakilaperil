<?php

declare(strict_types=1);

namespace App\Engagement\Http;

use App\I18n\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helpers for PUBLIC JSON endpoints (port of lib/forms/http.ts). They are unauthenticated, so the
 * CSRF concern is abuse from other sites: we require a same-origin `Origin` header (browsers always
 * send it on cross-site POSTs). Same response shapes as the reference: { ok, code, fieldErrors }.
 */
final class PublicJson
{
    public const MAX_JSON_BYTES = 32 * 1024;

    /** @param  array{fieldErrors?: array<string, list<string>>, retryAfterSec?: int}  $extra */
    public static function error(int $status, string $code, array $extra = []): JsonResponse
    {
        $headers = ['Cache-Control' => 'no-store'];
        if (! empty($extra['retryAfterSec'])) {
            $headers['Retry-After'] = (string) $extra['retryAfterSec'];
        }
        $body = ['ok' => false, 'code' => $code];
        if (isset($extra['fieldErrors'])) {
            $body['fieldErrors'] = self::publicFieldErrors($extra['fieldErrors']);
        }

        return new JsonResponse($body, $status, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Message keys the public forms can translate (`forms.errors.*` in resources/messages). */
    public const ERROR_KEYS = [
        'required', 'invalidEmail', 'tooLong', 'consentRequired', 'invalidUrl', 'invalid',
        'invalidAmount', 'amountOutOfRange', 'monthlyCardOnly',
    ];

    /**
     * The validators mirror Zod, so a wrong TYPE yields the library's English sentence
     * ("Invalid input: expected string, received number"). The API contract is message KEYS:
     * anything that is not a known key becomes "invalid".
     *
     * @param  array<string, list<string>>  $fieldErrors
     * @return array<string, list<string>>
     */
    public static function publicFieldErrors(array $fieldErrors): array
    {
        $out = [];
        foreach ($fieldErrors as $field => $messages) {
            $keys = [];
            foreach ($messages as $message) {
                $keys[] = in_array($message, self::ERROR_KEYS, true) ? $message : 'invalid';
            }
            $out[$field] = array_values(array_unique($keys));
        }

        return $out;
    }

    /** @param  array<string, mixed>  $data */
    public static function ok(array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['ok' => true, ...$data], $status, ['Cache-Control' => 'no-store'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * True when the request's Origin matches this site (SITE_URL) or the host this request was
     * addressed to. `getHttpHost()` only honours X-Forwarded-Host from a TRUSTED proxy, so a client
     * cannot widen the check by sending that header itself.
     */
    public static function isSameOrigin(Request $request): bool
    {
        $origin = $request->headers->get('origin');
        if ($origin === null || $origin === '') {
            return false;
        }
        $originHost = self::hostOf($origin);
        if ($originHost === null) {
            return false;
        }
        $allowed = [];
        $site = self::hostOf((string) config('sakila.site_url'));
        if ($site !== null) {
            $allowed[] = $site;
        }
        $host = $request->getHttpHost();
        if ($host !== '') {
            $allowed[] = strtolower($host);
        }

        return in_array($originHost, $allowed, true);
    }

    /** URL host incl. a non-default port, lowercase (like WHATWG URL.host). */
    private static function hostOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $default = ['http' => 80, 'https' => 443][strtolower($parts['scheme'])] ?? null;

        return $port !== null && $port !== $default ? "{$host}:{$port}" : $host;
    }

    /**
     * A small JSON object body, or null when missing, too large, malformed or not an object.
     *
     * @return array<string, mixed>|null
     */
    public static function readJson(Request $request): ?array
    {
        if (! str_contains((string) $request->headers->get('content-type', ''), 'application/json')) {
            return null;
        }
        if ((int) $request->headers->get('content-length', '0') > self::MAX_JSON_BYTES) {
            return null;
        }
        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_JSON_BYTES) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            return null;
        }

        return $data;
    }

    public static function localeOf(mixed $value): string
    {
        return Locales::isLocale($value) ? (string) $value : Locales::DEFAULT;
    }
}
