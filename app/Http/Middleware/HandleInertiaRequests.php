<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Permissions;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

/** Inertia for the CMS: root view + props shared with every admin page (see resources/js/types/inertia.ts). */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'admin';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();
        // Admin screens kept in the browser history are encrypted with a per-session key, and the
        // key is thrown away on sign-out (LoginController::destroy): Back cannot bring them back.
        Inertia::encryptHistory();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'image' => $user->image,
                ] : null,
                'capabilities' => $user instanceof User ? Permissions::capabilitiesFor($user->role) : [],
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'id' => $request->session()->get('flash_id'),
            ],
        ];
    }
}
