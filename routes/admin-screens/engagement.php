<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DonationsController;
use App\Http\Controllers\Admin\SubmissionsController;
use Illuminate\Support\Facades\Route;

// ENGAGEMENT: Inertia screens + CSV exports. Personal data is ADMIN+ only (submissions.manage, donations.read).
Route::middleware('capability:submissions.manage')->group(function (): void {
    Route::get('submissions', [SubmissionsController::class, 'index']);
    Route::get('submissions/export', [SubmissionsController::class, 'export']);
    Route::get('submissions/{type}/{id}', [SubmissionsController::class, 'show'])->where('type', 'contact|volunteer|membership|partner');
});
Route::middleware('capability:donations.read')->group(function (): void {
    Route::get('donations', [DonationsController::class, 'index']);
    Route::get('donations/export', [DonationsController::class, 'export']);
    Route::get('donations/{id}', [DonationsController::class, 'show']);
});
