<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\NavigationController;
use Illuminate\Support\Facades\Route;

// Menus.
Route::post('navigation/save', [NavigationController::class, 'save'])->middleware('capability:navigation.manage');
