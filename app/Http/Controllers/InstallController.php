<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Install\FirstAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * One-time /install page for hosts without SSH: creates the first SUPER_ADMIN.
 * Only exists while (1) INSTALL_TOKEN is set in .env (16+ chars) AND (2) no active SUPER_ADMIN
 * exists — otherwise every request is a plain 404. The token must be typed into the form
 * (never in the URL, so it can't leak via logs/Referer). 5 attempts per 15 minutes per IP.
 * After success: remove INSTALL_TOKEN from .env (the page is already disabled by rule 2).
 */
final class InstallController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function show(): View
    {
        $this->ensureAvailable();

        return view('install.first-admin', ['installErrors' => [], 'old' => []]);
    }

    public function store(Request $request): RedirectResponse|Response
    {
        $this->ensureAvailable();
        $key = 'install:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->view('install.first-admin', [
                'installErrors' => ['form' => 'Too many attempts. Try again in '.(int) ceil(RateLimiter::availableIn($key) / 60).' minute(s).'],
                'old' => [],
            ], 429);
        }
        $name = (string) $request->input('name', '');
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $token = (string) $request->input('token', '');
        $old = ['name' => $name, 'email' => $email];

        if (! hash_equals(self::token(), $token)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            logger()->warning('[install] wrong install token', ['ip' => $request->ip()]);

            return response()->view('install.first-admin', ['installErrors' => ['token' => 'The install token is not correct.'], 'old' => $old], 422);
        }
        if (($errors = FirstAdmin::validate($name, $email, $password)) !== []) {
            return response()->view('install.first-admin', ['installErrors' => $errors, 'old' => $old], 422);
        }
        try {
            FirstAdmin::create($name, $email, $password, 'install-page');
        } catch (Throwable) {
            abort(404);
        }
        RateLimiter::clear($key);

        return redirect('/admin/login')->with('installed', true);
    }

    private function ensureAvailable(): void
    {
        abort_unless(mb_strlen(self::token()) >= 16 && FirstAdmin::needed(), 404);
    }

    private static function token(): string
    {
        return (string) config('sakila.install_token');
    }
}
