<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\GalleryController;
use Illuminate\Support\Facades\Route;

// /admin/actions/gallery/* (capabilities are checked inside each action, like the reference).
Route::post('gallery/save', [GalleryController::class, 'save']);
Route::post('gallery/set-status', [GalleryController::class, 'setStatus']);
Route::post('gallery/delete', [GalleryController::class, 'delete']);
