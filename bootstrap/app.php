<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\EnsureProductionConfig;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireCapability;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use App\I18n\Locales;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Public site: the cookie-less `site` group (no session, no cookies, no CSRF) so anonymous
        // visitors never get a Set-Cookie — like the reference, and no session file per visitor.
        then: function (): void {
            Route::middleware('site')->group(base_path('routes/site.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('site', [SubstituteBindings::class]);
        $middleware->alias([
            'admin.auth' => AdminAuthenticate::class,
            'capability' => RequireCapability::class,
            'inertia' => HandleInertiaRequests::class,
            'locale' => SetLocale::class,
        ]);
        // Reverse proxies are decided per request from config (works with config:cache), see
        // App\Http\Middleware\TrustProxies and config/sakila.php (TRUST_PROXY, TRUSTED_PROXIES).
        $middleware->replace(Illuminate\Http\Middleware\TrustProxies::class, TrustProxies::class);
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->append(SecurityHeaders::class);
        // Production only: 503 with the list of broken settings instead of serving wrong links.
        $middleware->prepend(EnsureProductionConfig::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Public 5xx inside the site chrome (port of app/[locale]/(site)/error.tsx). If the chrome
        // itself can't render (e.g. database down), Laravel falls back to the self-contained
        // resources/views/errors/500.blade.php.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (config('app.debug') || $e instanceof HttpExceptionInterface || ! $request->isMethod('GET')) {
                return null;
            }
            $locale = $request->segment(1);
            if (! Locales::isLocale($locale)) {
                return null;
            }
            try {
                app()->setLocale($locale);

                return response()->view('site.pages.error', [
                    'locale' => $locale,
                    'meta' => ['title' => t('errors.errorTitle'), 'robots' => 'noindex'],
                ], 500);
            } catch (Throwable) {
                return null;
            }
        });
    })->create();
