<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Which reverse proxies may tell us the visitor's address and scheme (config/sakila.php).
 *
 * Read from config at request time (not `env()` in bootstrap/app.php), so it also works after
 * `php artisan config:cache`. With TRUST_PROXY off nothing is trusted: the connecting address is
 * the client, as on plain cPanel hosting.
 *
 * Symfony then resolves the client IP as the right-most forwarded address that is not one of the
 * trusted proxies. X-Forwarded-Host is deliberately not in the trusted set: the host always comes
 * from the request itself, so a forged header cannot change generated URLs or origin checks.
 */
final class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT;

    /** @return list<string>|string|null */
    protected function proxies(): array|string|null
    {
        return self::trusted();
    }

    protected function headers(): int
    {
        return $this->headers;
    }

    /**
     * @return list<string>|string|null null = trust nobody, '*' = the connecting address, or IPs/CIDRs
     */
    public static function trusted(): array|string|null
    {
        if (! filter_var(config('sakila.trust_proxy'), FILTER_VALIDATE_BOOL)) {
            return null;
        }
        $setting = trim((string) config('sakila.trusted_proxies', 'cloudflare'));
        if ($setting === '*') {
            return '*';
        }
        $out = [];
        foreach (array_filter(array_map(trim(...), explode(',', $setting === '' ? 'cloudflare' : $setting))) as $entry) {
            if (strtolower($entry) === 'cloudflare') {
                array_push($out, ...array_values((array) config('sakila.cloudflare_ranges', [])));
            } else {
                $out[] = $entry;
            }
        }

        return array_values(array_unique($out));
    }
}
