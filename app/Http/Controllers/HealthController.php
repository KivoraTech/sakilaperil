<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GET /api/health — for uptime monitors and post-deploy checks (port of the reference
 * app/api/health/route.ts). 200 {"status":"ok"} when the database answers, 503 otherwise.
 * Exposes no versions, hostnames or error details (those go to the log only).
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        $started = microtime(true);
        try {
            DB::select('SELECT 1');

            return response()->json(
                ['status' => 'ok', 'db' => 'ok', 'ms' => (int) round((microtime(true) - $started) * 1000)],
                200,
                ['Cache-Control' => 'no-store'],
            );
        } catch (Throwable $e) {
            logger()->error('[health] database check failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error', 'db' => 'unreachable'], 503, ['Cache-Control' => 'no-store']);
        }
    }
}
