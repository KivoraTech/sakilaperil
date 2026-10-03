<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ContentPreviewController;
use App\Http\Controllers\Admin\MediaController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| Routes outside /admin (required from routes/web.php):
|   POST /api/media                                  uploads (web stack: session + same-origin check)
|   /preview/{locale}/{news,projects,events}/{id}    authenticated draft previews (noindex/no-store)
*/

// Same protection as the reference (app/api/media/route.ts): the Origin header must be this site
// (checked in the controller) and the SameSite=Lax session cookie is never sent on cross-site POSTs.
// The CSRF token check is skipped so API clients get the reference's 401/403 instead of 419.
Route::post('api/media', [MediaController::class, 'upload'])->withoutMiddleware(ValidateCsrfToken::class);

Route::prefix('preview/{locale}')->where(['locale' => 'it|en'])
    ->middleware(['admin.auth', 'capability:content.create', 'locale'])
    ->group(function (): void {
        Route::get('news/{id}', [ContentPreviewController::class, 'news']);
        Route::get('projects/{id}', [ContentPreviewController::class, 'project']);
        Route::get('events/{id}', [ContentPreviewController::class, 'event']);
    });
