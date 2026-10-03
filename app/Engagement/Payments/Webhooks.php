<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use App\Models\PaymentWebhookEvent;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Idempotent webhook processing via PaymentWebhookEvent(provider, eventId) (port of lib/payments/webhooks.ts):
 * first delivery runs the handler; a re-delivery of a processed event is skipped ("duplicate");
 * a re-delivery after a failed attempt runs again (providers retry on non-2xx).
 */
final class Webhooks
{
    /** @return 'processed'|'duplicate' */
    public static function processOnce(string $provider, string $eventId, string $type, Closure $handler): string
    {
        try {
            PaymentWebhookEvent::query()->create(['provider' => $provider, 'eventId' => $eventId, 'type' => mb_substr($type, 0, 100)]);
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $existing = PaymentWebhookEvent::query()->where('provider', $provider)->where('eventId', $eventId)->first();
            if ($existing?->processedAt !== null) {
                return 'duplicate';
            }
        }
        $query = PaymentWebhookEvent::query()->where('provider', $provider)->where('eventId', $eventId);
        try {
            $handler();
            $query->update(['processedAt' => Carbon::now('UTC')->format('Y-m-d H:i:s.v'), 'error' => null]);

            return 'processed';
        } catch (Throwable $e) {
            try {
                $query->update(['error' => mb_substr($e->getMessage() ?: 'error', 0, 500)]);
            } catch (Throwable) {
                // best effort
            }
            throw $e;
        }
    }
}
