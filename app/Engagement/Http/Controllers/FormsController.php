<?php

declare(strict_types=1);

namespace App\Engagement\Http\Controllers;

use App\Engagement\Forms\FormSchemas;
use App\Engagement\Forms\Submissions;
use App\Engagement\Http\PublicJson;
use App\Engagement\Spam\Guard;
use App\Engagement\Spam\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * POST /api/forms/{contact|volunteer|membership|partner} (port of app/api/forms/[type]/route.ts).
 * Body: form fields + { locale, hp_extra, startedAt, turnstileToken?, source? }.
 * Order: origin → JSON → anti-spam (rate limit, honeypot, time trap, Turnstile) → validation → DB.
 */
final class FormsController
{
    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (! FormSchemas::isFormType($type)) {
            return PublicJson::error(404, 'ERROR');
        }
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
        $guard = Guard::check($type, $meta->data);
        if (! $guard['ok']) {
            return PublicJson::error($guard['status'], $guard['code'], ['retryAfterSec' => $guard['retryAfterSec'] ?? 0]);
        }
        $parsed = FormSchemas::parse($type, $record);
        if (! $parsed->ok()) {
            RateLimiter::refund($type, $guard['ipHash']);

            return PublicJson::error(422, 'VALIDATION', ['fieldErrors' => $parsed->fieldErrors]);
        }
        try {
            Submissions::save($type, $parsed->data, PublicJson::localeOf($record['locale'] ?? null), $guard['ipHash']);

            return PublicJson::ok([], 201);
        } catch (Throwable $e) {
            Log::error("[forms] failed to save {$type} submission", ['error' => $e->getMessage()]);

            return PublicJson::error(500, 'ERROR');
        }
    }
}
