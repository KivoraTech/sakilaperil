<?php

declare(strict_types=1);

use App\Engagement\Http\Controllers\DonationsController;
use App\Engagement\Http\Controllers\FormsController;
use App\Engagement\Http\Controllers\NewsletterController;
use App\Engagement\Http\Controllers\WebhooksController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
| Public JSON endpoints (ENGAGEMENT; port of the reference app/api/**). Mounted under /api with the
| stateless `api` middleware group: no session, no CSRF token — public POSTs require a same-origin
| `Origin` header instead (App\Engagement\Http\PublicJson::isSameOrigin), webhooks are signed.
*/

Route::post('forms/{type}', FormsController::class)->where('type', '[a-z]+');

Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe']);
Route::get('newsletter/confirm', [NewsletterController::class, 'confirm']);
Route::post('newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);
Route::get('newsletter/unsubscribe', [NewsletterController::class, 'unsubscribeLanding']);

Route::post('donations', [DonationsController::class, 'start']);
Route::get('donations/stripe/return', [DonationsController::class, 'stripeReturn']);
Route::get('donations/paypal/return', [DonationsController::class, 'paypalReturn']);
Route::get('donations/khalti/return', [DonationsController::class, 'khaltiReturn']);
Route::get('donations/esewa/success', [DonationsController::class, 'esewaSuccess']);
Route::get('donations/esewa/failure/{ref}', [DonationsController::class, 'esewaFailure']);

Route::post('webhooks/stripe', [WebhooksController::class, 'stripe']);
Route::post('webhooks/paypal', [WebhooksController::class, 'paypal']);

// Uptime monitoring (database ping). `/up` (Laravel's boot check) stays too.
Route::get('health', HealthController::class);
