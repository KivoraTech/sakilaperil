<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ProjectsController;
use Illuminate\Support\Facades\Route;

// /admin/actions/projects/* (capabilities are checked inside each action, like the reference).
Route::post('projects/save', [ProjectsController::class, 'save']);
Route::post('projects/set-status', [ProjectsController::class, 'setStatus']);
Route::post('projects/delete', [ProjectsController::class, 'delete']);
