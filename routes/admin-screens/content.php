<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\EventsController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\NewsController;
use App\Http\Controllers\Admin\ProjectsController;
use App\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Route;

// Inertia screens /admin/{news,projects,events,gallery,reports,media}[/new|/{id}]
// (inside the admin + admin.auth group of routes/web.php). Actions: routes/admin/<area>.php.
Route::middleware('capability:content.create')->group(function (): void {
    Route::get('news', [NewsController::class, 'index']);
    Route::get('news/new', [NewsController::class, 'create']);
    Route::get('news/{id}', [NewsController::class, 'edit']);

    Route::get('projects', [ProjectsController::class, 'index']);
    Route::get('projects/new', [ProjectsController::class, 'create']);
    Route::get('projects/{id}', [ProjectsController::class, 'edit']);

    Route::get('events', [EventsController::class, 'index']);
    Route::get('events/new', [EventsController::class, 'create']);
    Route::get('events/{id}', [EventsController::class, 'edit']);

    Route::get('gallery', [GalleryController::class, 'index']);
    Route::get('reports', [ReportsController::class, 'index']);
});
Route::middleware('capability:content.editAll')->group(function (): void {
    Route::get('gallery/new', [GalleryController::class, 'create']);
    Route::get('gallery/{id}', [GalleryController::class, 'edit']);
    Route::get('reports/new', [ReportsController::class, 'create']);
    Route::get('reports/{id}', [ReportsController::class, 'edit']);
});
Route::get('media', [MediaController::class, 'index'])->middleware('capability:media.upload');
