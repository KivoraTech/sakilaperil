<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\View\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * app/[locale]/(site)/newsletter/page.tsx: sign-up + double opt-in result (?status=confirmed|invalid)
 * + unsubscribe confirmation (?unsubscribe=<token>). Forms are ENGAGEMENT partials.
 */
final class NewsletterPageController extends SiteController
{
    public function __invoke(Request $request): Response
    {
        $locale = $this->locale();
        $statusRaw = $request->query('status');
        $status = in_array($statusRaw, ['confirmed', 'invalid'], true) ? $statusRaw : null;
        $unsub = $request->query('unsubscribe');
        $unsubscribe = is_string($unsub) && strlen($unsub) < 1000 ? $unsub : null;
        $meta = $this->meta(Seo::sameAlternates('/newsletter'), t('newsletter.pageTitle'), t('newsletter.pageIntro'));
        // A utility page (sign-up, confirmation result, unsubscribe): not a search result. Variants
        // with tokens must not be followed either.
        $meta['robots'] = $request->query('status') !== null || $request->query('unsubscribe') !== null ? 'noindex, nofollow' : 'noindex, follow';

        return response()->view('site.pages.newsletter', [
            'locale' => $locale,
            'status' => $status,
            'unsubscribe' => $unsubscribe,
            'meta' => $meta,
        ]);
    }
}
