<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

// Site settings.
Route::post('settings/update', [SettingsController::class, 'update'])->middleware('capability:settings.manage');
