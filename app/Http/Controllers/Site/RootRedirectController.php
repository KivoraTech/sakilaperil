<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\I18n\Locales;
use Illuminate\Http\RedirectResponse;

/** `/` → `/it` (301: the locale-less root is never a page of its own). A controller (not a closure) so `route:cache` works. */
final class RootRedirectController
{
    public function __invoke(): RedirectResponse
    {
        return redirect('/'.Locales::DEFAULT, 301);
    }
}
