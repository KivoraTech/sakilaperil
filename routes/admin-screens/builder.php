<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminNotFound;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NavigationController;
use App\Http\Controllers\Admin\PagesController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UsersController;
use Illuminate\Support\Facades\Route;

// Admin screens for pages, menus, settings, users and audit. Loaded inside the authenticated /admin group.
Route::get('/', DashboardController::class)->middleware('capability:dashboard.view');
Route::get('pages', [PagesController::class, 'index'])->middleware('capability:pages.view');
Route::get('pages/{id}', [PagesController::class, 'edit'])->middleware('capability:pages.view');
Route::get('navigation', [NavigationController::class, 'index'])->middleware('capability:navigation.manage');
Route::get('settings', [SettingsController::class, 'index'])->middleware('capability:settings.manage');
Route::get('users', [UsersController::class, 'index'])->middleware('capability:users.manage');
Route::get('audit', [AuditController::class, 'index'])->middleware('capability:audit.read');

// Unknown /admin/… addresses: 404 inside the admin shell (fallback routes are always matched last).
Route::fallback(AdminNotFound::class);
