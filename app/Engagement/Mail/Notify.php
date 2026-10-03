<?php

declare(strict_types=1);

namespace App\Engagement\Mail;

use Illuminate\Support\Facades\Log;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Team notification for new submissions/donations (port of lib/forms/notify.ts).
 * Mail configured + NOTIFY_EMAIL_TO set → a short email with only a CMS link (GDPR data
 * minimisation); otherwise log-only (type + id, no personal data). Never throws: the row is already stored.
 */
final class Notify
{
    private const SUBJECTS = [
        'contact' => 'New contact message',
        'volunteer' => 'New volunteer application',
        'membership' => 'New membership application',
        'partner' => 'New partner enquiry',
        'donation' => 'Donation confirmed',
    ];

    public static function isConfigured(): bool
    {
        return Mailer::isConfigured() && (string) config('engagement.notify_email_to') !== '';
    }

    public static function team(string $kind, string $id, ?string $summary = null): void
    {
        $adminPath = $kind === 'donation' ? "/admin/donations/{$id}" : "/admin/submissions/{$kind}/{$id}";
        $link = rtrim((string) config('sakila.site_url'), '/').$adminPath;
        if (! self::isConfigured()) {
            Log::info("[notify] {$kind} {$id} (email not configured; log only)");

            return;
        }
        $subject = self::SUBJECTS[$kind] ?? 'New submission';
        // After the response has been sent (fastcgi/litespeed_finish_request): the visitor never
        // waits for the team's mail server, and a mail failure can't affect their submission.
        defer(function () use ($kind, $id, $subject, $summary, $link): void {
            try {
                Mailer::send(
                    (string) config('engagement.notify_email_to'),
                    "[Sakila] {$subject}",
                    $subject.($summary ? ": {$summary}" : '')."\n\nOpen in the CMS: {$link}\n",
                );
            } catch (Throwable $e) {
                Log::error("[notify] failed to send {$kind} {$id} notification", ['error' => $e->getMessage()]);
            }
        });
    }
}
