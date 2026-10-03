<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DonationsController;
use Illuminate\Support\Facades\Route;

// ENGAGEMENT: /admin/actions/donations/* (ADMIN+). Online donations are read-only.
Route::middleware('capability:donations.manage')->group(function (): void {
    Route::post('donations/record-bank-transfer', [DonationsController::class, 'recordBankTransfer']);
    Route::post('donations/set-manual-status', [DonationsController::class, 'setManualStatus']);
});
