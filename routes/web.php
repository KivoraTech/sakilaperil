<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PasswordResetController;
use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

/*
| Route map (mirrors the Next.js reference app's URL structure exactly):
|   /                    → 307 /it
|   /{it|en}/…           public site (Blade)
|   /admin/…             CMS (Inertia + React)
|   /preview/{locale}/…  authenticated draft previews
|   /api/…               JSON endpoints (forms, media, webhooks, health)
|   /uploads/{key}       locally stored media (UploadsController)
*/

// ── Admin ────────────────────────────────────────────────────────────────────────────────
Route::prefix('admin')->middleware('inertia')->group(function (): void {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
    Route::post('logout', [LoginController::class, 'destroy'])->middleware('admin.auth');
    Route::get('forgot-password', [PasswordResetController::class, 'request']);
    Route::post('forgot-password', [PasswordResetController::class, 'send']);
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit']);
    Route::post('reset-password', [PasswordResetController::class, 'update']);

    Route::middleware('admin.auth')->group(function (): void {
        // Dashboard, pages, menus, settings, users, audit.
        require base_path('routes/admin-screens/builder.php');
        // News, projects, events, gallery, reports, media.
        require base_path('routes/admin-screens/content.php');
        Route::inertia('access-denied', 'admin/access-denied');

        // Admin actions (ActionResult JSON; see resources/js/lib/actions/client.ts). Each area adds
        // `Route::post('actions/<area>/<verb>', …)->middleware('capability:…')` in its own route file.
        Route::prefix('actions')->group(function (): void {
            foreach (glob(base_path('routes/admin/*.php')) ?: [] as $file) {
                require $file;
            }
        });

        // Submissions inbox + donations, CSV exports.
        require base_path('routes/admin-screens/engagement.php');
    });
});

// ── Draft previews: session-gated, noindex/no-store (SecurityHeaders) ──────────
Route::get('preview/{locale}/pages/{pageId}', [PreviewController::class, 'page'])
    ->where('locale', 'it|en')
    ->middleware(['admin.auth', 'capability:pages.view', 'locale']);

// ── Uploads API + content previews ──────────────────────────────────────
require base_path('routes/content.php');

// ── Public site: routes/site.php, registered in bootstrap/app.php under the
// cookie-less `site` middleware group (no session/cookies/CSRF for anonymous visitors). ─────

// ── One-time first-admin setup without SSH (404 unless INSTALL_TOKEN is set AND no super admin exists) ──
Route::get('install', [InstallController::class, 'show']);
Route::post('install', [InstallController::class, 'store']);
