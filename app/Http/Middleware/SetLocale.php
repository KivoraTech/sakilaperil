<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\I18n\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Public routes are /{locale}/…: set the app locale (port of next-intl's [locale] segment). */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        if (! Locales::isLocale($locale)) {
            abort(404);
        }
        app()->setLocale((string) $locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
