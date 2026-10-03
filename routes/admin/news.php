<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\NewsController;
use Illuminate\Support\Facades\Route;

// /admin/actions/news/* (capabilities are checked inside each action, like the reference).
Route::post('news/save', [NewsController::class, 'save']);
Route::post('news/set-status', [NewsController::class, 'setStatus']);
Route::post('news/delete', [NewsController::class, 'delete']);
Route::post('news/create-category', [NewsController::class, 'createCategory']);
