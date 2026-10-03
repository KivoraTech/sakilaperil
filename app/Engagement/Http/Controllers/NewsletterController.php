<?php

declare(strict_types=1);

namespace App\Engagement\Http\Controllers;

use App\Engagement\Forms\FormSchemas;
use App\Engagement\Http\PublicJson;
use App\Engagement\Newsletter\Newsletter;
use App\Engagement\Spam\Guard;
use App\Engagement\Spam\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/** /api/newsletter/* (port of app/api/newsletter/**). */
final class NewsletterController
{
    /** POST /api/newsletter/subscribe — { email, consent, locale, source?, hp_extra, startedAt } */
    public function subscribe(Request $request): JsonResponse
    {
        if (! PublicJson::isSameOrigin($request)) {
            return PublicJson::error(403, 'FORBIDDEN');
        }
        $record = PublicJson::readJson($request);
        if ($record === null) {
            return PublicJson::error(400, 'VALIDATION');
        }
        $meta = FormSchemas::spamMeta($record);
        if (! $meta->ok()) {
            return PublicJson::error(400, 'SPAM');
        }
        $guard = Guard::check('newsletter', $meta->data);
        if (! $guard['ok']) {
            return PublicJson::error($guard['status'], $guard['code'], ['retryAfterSec' => $guard['retryAfterSec'] ?? 0]);
        }
        $parsed = FormSchemas::newsletter($record);
        if (! $parsed->ok()) {
            RateLimiter::refund('newsletter', $guard['ipHash']);

            return PublicJson::error(422, 'VALIDATION', ['fieldErrors' => $parsed->fieldErrors]);
        }
        try {
            Newsletter::subscribe($parsed->data['email'], PublicJson::localeOf($record['locale'] ?? null), $meta->data['source'] ?? null);

            // Same answer whether or not the address was already on the list (no enumeration).
            return PublicJson::ok([], 202);
        } catch (Throwable $e) {
            Log::error('[newsletter] subscribe failed', ['error' => $e->getMessage()]);

            return PublicJson::error(500, 'ERROR');
        }
    }

    /** GET /api/newsletter/confirm?token=…&locale=it — double opt-in landing. */
    public function confirm(Request $request): RedirectResponse
    {
        $locale = PublicJson::localeOf($request->query('locale'));
        try {
            $ok = Newsletter::confirm($request->query('token'));
        } catch (Throwable) {
            $ok = false;
        }
        $base = rtrim((string) config('sakila.site_url'), '/');

        return redirect()->away("{$base}/{$locale}/newsletter?status=".($ok ? 'confirmed' : 'invalid'), 303);
    }

    /**
     * POST /api/newsletter/unsubscribe — JSON { token } from /{locale}/newsletter (same-origin), or
     * RFC 8058 one-click: POST ?token=… from mail clients.
     */
    public function unsubscribe(Request $request): JsonResponse|Response
    {
        $queryToken = $request->query('token');
        if (is_string($queryToken) && $queryToken !== '') {
            try {
                $ok = Newsletter::unsubscribeWithToken($queryToken);
            } catch (Throwable) {
                $ok = false;
            }

            return new Response('', $ok ? 200 : 400);
        }
        if (! PublicJson::isSameOrigin($request)) {
            return PublicJson::error(403, 'FORBIDDEN');
        }
        $body = PublicJson::readJson($request);
        try {
            $ok = Newsletter::unsubscribeWithToken($body['token'] ?? null);
        } catch (Throwable) {
            $ok = false;
        }

        return $ok ? PublicJson::ok() : PublicJson::error(400, 'VALIDATION');
    }

    /** GET only redirects to the confirmation page, so link scanners cannot unsubscribe people. */
    public function unsubscribeLanding(Request $request): RedirectResponse
    {
        $locale = PublicJson::localeOf($request->query('locale'));
        $token = (string) ($request->query('token') ?? '');
        $base = rtrim((string) config('sakila.site_url'), '/');

        return redirect()->away("{$base}/{$locale}/newsletter?unsubscribe=".rawurlencode($token), 303);
    }
}
