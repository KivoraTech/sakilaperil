<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

// /admin/actions/media/* (update/delete check their capability inside the action, like the
// reference; the read-only library actions need the same capability as the Media screen).
Route::post('media/search', [MediaController::class, 'search'])->middleware('capability:media.upload');
Route::post('media/get', [MediaController::class, 'get'])->middleware('capability:media.upload');
Route::post('media/update', [MediaController::class, 'update']);
Route::post('media/usage', [MediaController::class, 'usage'])->middleware('capability:media.upload');
Route::post('media/delete', [MediaController::class, 'delete']);
