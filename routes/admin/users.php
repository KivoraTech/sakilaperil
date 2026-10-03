<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UsersController as Users;
use Illuminate\Support\Facades\Route;

// User management.
Route::middleware('capability:users.manage')->group(function (): void {
    Route::post('users/create', [Users::class, 'create']);
    Route::post('users/change-role', [Users::class, 'changeRole']);
    Route::post('users/set-active', [Users::class, 'setActive']);
    Route::post('users/revoke-sessions', [Users::class, 'revokeSessions']);
    Route::post('users/reset-password', [Users::class, 'resetPassword']);
    Route::post('users/rename', [Users::class, 'rename']);
});
