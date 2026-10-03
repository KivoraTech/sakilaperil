<?php

declare(strict_types=1);

use App\Http\Controllers\Site\DonateController;
use App\Http\Controllers\Site\EventsController;
use App\Http\Controllers\Site\FallbackController;
use App\Http\Controllers\Site\GalleryController;
use App\Http\Controllers\Site\ImageController;
use App\Http\Controllers\Site\NewsController;
use App\Http\Controllers\Site\NewsletterPageController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ProjectsController;
use App\Http\Controllers\Site\RootRedirectController;
use App\Http\Controllers\Site\SeoFilesController;
use App\Http\Controllers\UploadsController;
use Illuminate\Support\Facades\Route;

/*
| Public site — same URLs as the reference app/[locale]/(site)/**:
|   /{it|en}                      home (page "home")
|   /{it|en}/projects[/slug]      /news[/slug]   /events[/slug][/ics]   /gallery[/slug]
|   /{it|en}/donate[/thank-you|/cancelled]       /newsletter
|   /{it|en}/{page-slug}          CMS pages (localized slugs)
|   /sitemap.xml  /robots.txt  /_img (next/image equivalent)
| Anything else under /{it|en} renders the site 404; unprefixed public paths 307 → /it/….
*/

Route::get('/', RootRedirectController::class);
Route::get('/uploads/{key}', UploadsController::class)->where('key', '.*');
Route::get('/sitemap.xml', [SeoFilesController::class, 'sitemap']);
Route::get('/robots.txt', [SeoFilesController::class, 'robots']);
Route::get('/_img', ImageController::class)->name('site.image');

Route::prefix('{locale}')->where(['locale' => 'it|en'])->middleware('locale')->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('site.home');
    Route::get('projects', [ProjectsController::class, 'index']);
    Route::get('projects/{slug}', [ProjectsController::class, 'show']);
    Route::get('news', [NewsController::class, 'index']);
    Route::get('news/{slug}', [NewsController::class, 'show']);
    Route::get('events', [EventsController::class, 'index']);
    Route::get('events/{slug}', [EventsController::class, 'show']);
    Route::get('events/{slug}/ics', [EventsController::class, 'ics']);
    Route::get('gallery', [GalleryController::class, 'index']);
    Route::get('gallery/{slug}', [GalleryController::class, 'show']);
    Route::get('donate', [DonateController::class, 'index']);
    Route::get('donate/thank-you', [DonateController::class, 'thankYou']);
    Route::get('donate/cancelled', [DonateController::class, 'cancelled']);
    Route::get('newsletter', NewsletterPageController::class);
    Route::get('{path}', [PageController::class, 'show'])->where('path', '.+');
});

Route::fallback(FallbackController::class);
