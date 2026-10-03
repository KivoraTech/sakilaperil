<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\LoginThrottle;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\RequestInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/** Port of app/admin/login + lib/auth/actions.ts (loginAction / logoutAction). */
final class LoginController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/admin');
        }
        $next = $request->query('next');

        $status = $request->session()->get('status');

        return Inertia::render('auth/login', ['next' => is_string($next) ? $next : null, 'status' => is_string($status) ? $status : null])
            ->withViewData(['title' => 'Sign in']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->only('email', 'password', 'next'), [
            'email' => ['required', 'email', 'max:200'],
            'password' => ['required', 'string', 'max:128'],
            'next' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors(['form' => 'Enter a valid email and password.'] + $validator->errors()->toArray());
        }
        $email = mb_strtolower(trim((string) $request->input('email')));
        $ip = RequestInfo::clientIp();

        $throttle = LoginThrottle::check($email, $ip);
        if (! $throttle['allowed']) {
            return back()->withErrors(['form' => 'Too many attempts. Try again in '.(int) ceil($throttle['retryAfter'] / 60).' minute(s).']);
        }

        if (! Auth::attempt(['email' => $email, 'password' => (string) $request->input('password')])) {
            LoginThrottle::record($email, $ip, false);
            $known = User::query()->where('email', $email)->value('id');
            Audit::log(AuditAction::USER_LOGIN_FAILED, is_string($known) ? $known : null, 'User', is_string($known) ? $known : null, ['reason' => 'INVALID_EMAIL_OR_PASSWORD']);

            // Same message for unknown email, wrong password and deactivated user (no enumeration).
            return back()->withErrors(['form' => 'Invalid email or password.']);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = Auth::user();
        LoginThrottle::record($email, $ip, true);
        $user->forceFill(['lastLoginAt' => now('UTC')])->save();
        Audit::log(AuditAction::USER_LOGIN, $user->id, 'User', $user->id);

        return redirect(self::safeNext($request->input('next')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory(); // the screens in the browser history become unreadable (shared computers)
        if ($user instanceof User) {
            Audit::log(AuditAction::USER_LOGOUT, $user->id, 'User', $user->id);
        }

        return redirect('/admin/login');
    }

    /** Only allow redirects back into the admin (prevents open redirects). */
    public static function safeNext(mixed $next): string
    {
        if (is_string($next) && preg_match('#^/admin(/[\w\-/]*)?(\?[\w=&%-]*)?$#', $next) === 1 && ! str_starts_with($next, '/admin/login')) {
            return $next;
        }

        return '/admin';
    }
}
