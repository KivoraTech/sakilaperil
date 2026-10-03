<?php

/*
 * Test-only router for `php -S` (never shipped): boots the app, adds a sandbox page that renders
 * every engagement component, then lets Laravel handle the request. Used by sandbox.spec.ts:
 *   DB_DATABASE=sakila_laravel_engagement php -S 127.0.0.1:3340 -t public tests/Engagement/sandbox-server.php
 */

declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false; // static file (built assets)
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';

$app->booted(function () {
    Route::middleware('web')->get('/__engagement/{locale}', function (string $locale) {
        app()->setLocale($locale);

        return view()->file(__DIR__.'/sandbox.blade.php', ['locale' => $locale, 'token' => request('unsubscribe')]);
    });
});

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request = Request::capture());
$response->send();
$kernel->terminate($request, $response);
