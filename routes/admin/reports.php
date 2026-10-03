<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ReportsController;
use Illuminate\Support\Facades\Route;

// /admin/actions/reports/* (capabilities are checked inside each action, like the reference).
Route::post('reports/save', [ReportsController::class, 'save']);
Route::post('reports/set-status', [ReportsController::class, 'setStatus']);
Route::post('reports/delete', [ReportsController::class, 'delete']);
