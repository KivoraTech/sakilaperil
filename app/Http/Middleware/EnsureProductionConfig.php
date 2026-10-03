<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ProductionConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production, refuse to serve with broken settings instead of running with wrong absolute
 * links or no uploads folder. The response says which setting to fix (names only, no values);
 * the same list is in the log and in `php artisan sakila:config-check`.
 */
final class EnsureProductionConfig
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isProduction()) {
            return $next($request);
        }
        $errors = ProductionConfig::errors();
        if ($errors === []) {
            return $next($request);
        }
        Log::critical('[config] the site is not configured correctly', ['problems' => $errors]);
        $body = "This website is not configured correctly yet.\n\nThe site owner needs to fix these settings in the .env file:\n- "
            .implode("\n- ", $errors)."\n";

        return response($body, 503, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store', 'Retry-After' => '300']);
    }
}
