<?php

declare(strict_types=1);

namespace App\Engagement;

use App\Engagement\Newsletter\Newsletter;
use App\Enums\NewsletterStatus;
use App\Models\NewsletterSubscriber;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Carbon;

/**
 * Retention rules for engagement data, for the daily `sakila:housekeeping` command:
 *
 *     $counts = \App\Engagement\Housekeeping::prune($dryRun);
 *
 * Deleted automatically (no value once stale):
 *  - newsletter sign-ups never confirmed within Newsletter::CONFIRM_TTL_DAYS (their link expired);
 *  - payment webhook event ids older than WEBHOOK_EVENT_DAYS (kept only to ignore provider retries,
 *    which stop after a few days).
 * (Rate-limit windows are already pruned by the command itself.)
 *
 * NEVER deleted here: contact / volunteer / membership / partner submissions and donations. They are
 * the association's records (and donations are accounting data); the team archives or deletes them
 * in the admin. Recommended retention, to state in the privacy notice: messages 24 months after the
 * last contact, donations 10 years (Italian accounting rules).
 */
final class Housekeeping
{
    public const WEBHOOK_EVENT_DAYS = 180;

    /** @return array{unconfirmedNewsletter: int, webhookEvents: int} */
    public static function prune(bool $dryRun = false): array
    {
        $now = Carbon::now('UTC');
        $newsletterCutoff = $now->copy()->subDays(Newsletter::CONFIRM_TTL_DAYS)->format('Y-m-d H:i:s.v');
        $webhookCutoff = $now->copy()->subDays(self::WEBHOOK_EVENT_DAYS)->format('Y-m-d H:i:s.v');

        if ($dryRun) {
            return [
                'unconfirmedNewsletter' => NewsletterSubscriber::query()
                    ->where('status', NewsletterStatus::PENDING->value)->whereNull('confirmedAt')->whereNotNull('provider')
                    ->where('updatedAt', '<', $newsletterCutoff)->count(),
                'webhookEvents' => PaymentWebhookEvent::query()->where('receivedAt', '<', $webhookCutoff)->count(),
            ];
        }

        return [
            'unconfirmedNewsletter' => Newsletter::pruneUnconfirmed(),
            'webhookEvents' => PaymentWebhookEvent::query()->where('receivedAt', '<', $webhookCutoff)->delete(),
        ];
    }
}
