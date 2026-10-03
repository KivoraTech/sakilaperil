<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Permissions;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('capability:pages.publish')`. Pages redirect to /admin/access-denied (like
 * requirePageCapability); JSON/API callers get 401/403 (like requireCapability).
 */
final class RequireCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $json = $request->expectsJson() && ! $request->header('X-Inertia');
        if (! $user instanceof User) {
            return $json ? response()->json(['ok' => false, 'code' => 'UNAUTHENTICATED'], 401) : redirect('/admin/login');
        }
        if (! Permissions::can($user->role, $capability)) {
            return $json ? response()->json(['ok' => false, 'code' => 'FORBIDDEN'], 403) : redirect('/admin/access-denied');
        }

        return $next($request);
    }
}
