<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SubmissionsController;
use Illuminate\Support\Facades\Route;

// ENGAGEMENT: /admin/actions/submissions/* (ADMIN+, personal data).
Route::middleware('capability:submissions.manage')->group(function (): void {
    Route::post('submissions/set-status', [SubmissionsController::class, 'setStatus']);
    Route::post('submissions/mark-read', [SubmissionsController::class, 'markRead']);
    Route::post('submissions/save-note', [SubmissionsController::class, 'saveNote']);
    Route::post('submissions/delete', [SubmissionsController::class, 'delete']);
});
