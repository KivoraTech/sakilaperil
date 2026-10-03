<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin pages: anonymous → /admin/login?next=…; the provider already refuses deactivated users
 * and unknown roles, so a revoked or deactivated account is signed out on its next request.
 */
final class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['ok' => false, 'code' => 'UNAUTHENTICATED'], 401);
        }
        $path = $request->getRequestUri();
        $target = $path !== '/admin' && str_starts_with($path, '/admin') ? '/admin/login?next='.urlencode($path) : '/admin/login';

        return redirect($target);
    }
}
