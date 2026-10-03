<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\EventsController;
use Illuminate\Support\Facades\Route;

// /admin/actions/events/* (capabilities are checked inside each action, like the reference).
Route::post('events/save', [EventsController::class, 'save']);
Route::post('events/set-status', [EventsController::class, 'setStatus']);
Route::post('events/delete', [EventsController::class, 'delete']);
