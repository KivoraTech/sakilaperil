<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Not found" INSIDE the admin shell (HTTP 404): a signed-in editor who opens a deleted item or a
 * mistyped admin address keeps the sidebar and gets a way back, instead of the public Italian 404.
 */
final class AdminNotFound
{
    public static function response(Request $request, string $what = 'page', string $backHref = '/admin', string $backLabel = 'Back to dashboard'): Response
    {
        return Inertia::render('admin/not-found', [
            'what' => $what,
            'backHref' => $backHref,
            'backLabel' => $backLabel,
        ])->toResponse($request)->setStatusCode(404);
    }

    /** Catch-all for unknown /admin/… addresses (registered as the admin group's fallback route). */
    public function __invoke(Request $request): Response
    {
        return self::response($request);
    }
}
