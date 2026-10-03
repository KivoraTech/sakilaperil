<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Engagement\Payments\PaymentConfig;
use App\I18n\Locales;
use App\Queries\Public\Donations;
use App\Queries\Public\Pages;
use App\Queries\Public\Projects;
use App\View\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** app/[locale]/(site)/donate/page.tsx, donate/thank-you/page.tsx and donate/cancelled/page.tsx. */
final class DonateController extends SiteController
{
    public function index(Request $request): Response
    {
        $locale = $this->locale();
        $amountRaw = ProjectsController::one($request->query('amount'));
        $amount = $amountRaw !== null && preg_match('/^\s*[+-]?\d+/', $amountRaw, $m) === 1 ? (int) $m[0] : null;
        $defaultAmount = $amount !== null && $amount >= 1 && $amount <= 100000 ? $amount : null;
        $projectParam = ProjectsController::one($request->query('project'));
        $projectParam = $projectParam !== null ? mb_substr($projectParam, 0, 120) : null;
        $project = $projectParam ? Projects::get($locale, $projectParam) : null;
        if ($project === null && $projectParam) {
            $byId = Projects::list($locale, ['ids' => [$projectParam]])[0] ?? null;
            $project = $byId !== null ? Projects::get($locale, $byId['slug']) : null;
        }

        $online = self::onlinePaymentsAvailable();

        return response()->view('site.pages.donate.index', [
            'locale' => $locale,
            'onlinePayments' => $online,
            'project' => $project,
            'defaultAmount' => $defaultAmount,
            'transparency' => Pages::byKey($locale, 'transparency'),
            'meta' => $this->meta(Seo::sameAlternates('/donate'), t('donatePage.title'), t($online ? 'donatePage.lead' : 'donatePage.leadBank')),
        ]);
    }

    /** Is at least one online payment provider set up? (Otherwise the site offers bank transfer only.) */
    public static function onlinePaymentsAvailable(): bool
    {
        try {
            foreach (PaymentConfig::publicStatus() as $provider) {
                if (! empty($provider['available'])) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    /** "Postcard from Nepal" (ideas #7): status from the DB only (never from the URL), no amounts in the URL. */
    public function thankYou(Request $request): Response
    {
        $locale = $this->locale();
        $refRaw = $request->query('ref');
        $ref = is_string($refRaw) ? mb_substr($refRaw, 0, 40) : null;
        $donation = null;
        if ($ref !== null && $ref !== '') {
            try {
                $donation = Donations::summary($ref);
            } catch (\Throwable) {
                $donation = null;
            }
        }
        $projectTitle = null;
        foreach ([$locale, 'it'] as $l) {
            foreach ($donation['project'] ?? [] as $p) {
                if ($projectTitle === null && $p['locale'] === $l) {
                    $projectTitle = $p['title'];
                }
            }
        }
        $settings = $this->settings();

        return response()->view('site.pages.donate.thank-you', [
            'locale' => $locale,
            'ref' => $ref,
            'donation' => $donation,
            'projectTitle' => $projectTitle,
            'settings' => $settings,
            'featured' => Projects::list($locale, ['featuredOnly' => true, 'limit' => 1])[0] ?? null,
            'photo' => $this->ogImage(null),
            'custom' => Locales::pick($settings['formMessages']['donation']['thankYou'] ?? null, $locale),
            'meta' => ['title' => t('thanks.metaTitle'), 'robots' => 'noindex, nofollow'],
        ]);
    }

    public function cancelled(): Response
    {
        return response()->view('site.pages.donate.cancelled', [
            'locale' => $this->locale(),
            'meta' => ['title' => t('donate.cancelledTitle'), 'robots' => 'noindex, nofollow'],
        ]);
    }
}
