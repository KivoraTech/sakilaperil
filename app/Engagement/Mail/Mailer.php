<?php

declare(strict_types=1);

namespace App\Engagement\Mail;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Outgoing email (port of lib/email): Laravel's mailer configured for a cPanel mailbox
 * (MAIL_MAILER=smtp, MAIL_HOST=mail.<domain>, MAIL_PORT=465 SSL or 587 STARTTLS, MAIL_USERNAME =
 * full mailbox address). When mail is not configured, send() returns false and the caller decides
 * (log-only notifications, local-only newsletter storage). Never logs message bodies (personal data).
 */
final class Mailer
{
    public static function isConfigured(): bool
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        if ($from === '' || str_ends_with($from, '@example.org') || str_ends_with($from, '@example.com')) {
            return false;
        }
        if ($mailer === 'smtp') {
            return (string) config('mail.mailers.smtp.host') !== '';
        }

        return in_array($mailer, ['sendmail', 'ses', 'postmark', 'resend', 'mailgun', 'array'], true);
    }

    /** Sends a plain-text email (+ optional HTML). Returns false when mail is not configured; throws on SMTP errors. */
    public static function send(string $to, string $subject, string $text, ?string $html = null, ?string $replyTo = null): bool
    {
        if (! self::isConfigured()) {
            return false;
        }
        $build = function (Message $m) use ($to, $subject, $replyTo): void {
            $m->to($to)->subject($subject);
            if ($replyTo !== null) {
                $m->replyTo($replyTo);
            }
        };
        if ($html !== null) {
            Mail::send(['html' => new HtmlString($html), 'text' => new HtmlString($text)], [], $build);
        } else {
            Mail::raw($text, $build);
        }

        return true;
    }

    /**
     * Sends after the HTTP response has been delivered (`defer`), so the caller's response time does
     * not depend on the mail server — and therefore cannot reveal whether an email was sent at all
     * (password-reset and sign-up flows must answer identically for known and unknown addresses).
     * Failures are logged, never thrown.
     */
    public static function sendAfterResponse(string $to, string $subject, string $text, ?string $html = null, ?string $replyTo = null): void
    {
        defer(static function () use ($to, $subject, $text, $html, $replyTo): void {
            try {
                self::send($to, $subject, $text, $html, $replyTo);
            } catch (Throwable $e) {
                Log::error('[mail] deferred send failed', ['error' => $e->getMessage()]);
            }
        });
    }
}
