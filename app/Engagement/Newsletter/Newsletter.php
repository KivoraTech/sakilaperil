<?php

declare(strict_types=1);

namespace App\Engagement\Newsletter;

use App\Enums\NewsletterStatus;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Newsletter subscriptions with double opt-in (port of lib/newsletter/index.ts). */
final class Newsletter
{
    private static function secret(): string
    {
        $secret = (string) config('engagement.newsletter.secret');
        if (str_starts_with($secret, 'base64:')) {
            $secret = (string) base64_decode(substr($secret, 7), true);
        }

        return $secret !== '' ? $secret : 'sakila-newsletter';
    }

    /** How long a "confirm your subscription" link stays valid. */
    public const CONFIRM_TTL_DAYS = 30;

    /**
     * Confirmation tokens expire after CONFIRM_TTL_DAYS (a leaked or forgotten link cannot confirm an
     * address months later); unsubscribe tokens never expire (the link in every email must keep working).
     */
    public static function token(string $purpose, string $email): string
    {
        return $purpose === 'confirm'
            ? Tokens::createTimed(self::secret(), $purpose, $email)
            : Tokens::create(self::secret(), $purpose, $email);
    }

    public static function readToken(string $purpose, mixed $token): ?string
    {
        return $purpose === 'confirm'
            ? Tokens::verifyTimed(self::secret(), $purpose, $token, self::CONFIRM_TTL_DAYS * 86400)
            : Tokens::verify(self::secret(), $purpose, $token);
    }

    /**
     * Housekeeping (called by `sakila:housekeeping`): delete sign-ups that were never confirmed.
     * Their confirmation link has expired, so the address was never verified; keeping it would
     * store personal data without a purpose. Kept: confirmed and unsubscribed rows (the latter as
     * the record that the address must not be mailed) and sign-ups stored while no email provider
     * was configured (`provider` NULL: no confirmation was ever sent; the team syncs them by hand).
     */
    public static function pruneUnconfirmed(?int $olderThanDays = null): int
    {
        $days = max($olderThanDays ?? self::CONFIRM_TTL_DAYS, self::CONFIRM_TTL_DAYS);
        $cutoff = Carbon::now('UTC')->subDays($days)->format('Y-m-d H:i:s.v');

        return NewsletterSubscriber::query()
            ->where('status', NewsletterStatus::PENDING->value)
            ->whereNull('confirmedAt')
            ->whereNotNull('provider')
            ->where('updatedAt', '<', $cutoff)
            ->delete();
    }

    public static function unsubscribeUrl(string $email, string $locale): string
    {
        return rtrim((string) config('sakila.site_url'), '/')."/{$locale}/newsletter?unsubscribe=".rawurlencode(self::token('unsubscribe', $email));
    }

    /**
     * Store (or re-activate) a subscriber and start the provider's double opt-in. Never reveals
     * whether the email was already subscribed (the caller always answers "check your inbox").
     */
    public static function subscribe(string $email, string $locale, ?string $source = null): void
    {
        $existing = NewsletterSubscriber::query()->where('email', $email)->first();
        if ($existing?->status === NewsletterStatus::SUBSCRIBED) {
            return;
        }
        $provider = Providers::active();
        $now = Carbon::now('UTC');
        $row = $existing ?? new NewsletterSubscriber(['email' => $email]);
        $row->fill([
            'locale' => $locale,
            'status' => NewsletterStatus::PENDING,
            'source' => $source ?? $existing?->source,
            'consentAt' => $now,
            'unsubscribedAt' => null,
            'provider' => $provider,
        ]);
        try {
            $row->save();
        } catch (UniqueConstraintViolationException) {
            // The same address was submitted twice at the same moment (two tabs, a double tap): the
            // other request created the row and starts the opt-in, so this one has nothing left to do.
            return;
        }

        if ($provider === null) {
            return; // stored locally; the team can export/sync later
        }
        $confirmUrl = rtrim((string) config('sakila.site_url'), '/').'/api/newsletter/confirm?token='
            .rawurlencode(self::token('confirm', $row->email))."&locale={$locale}";
        $startOptIn = static function () use ($provider, $row, $locale, $confirmUrl): void {
            try {
                $result = Providers::subscribe($provider, $row->email, $locale, $confirmUrl);
                if ($result['ok']) {
                    $row->update(['syncError' => null, 'providerId' => $result['providerId'] ?? $row->providerId]);
                } else {
                    $row->update(['syncError' => mb_substr($result['error'], 0, 500)]);
                }
            } catch (Throwable $e) {
                Log::error('[newsletter] provider subscribe failed', ['error' => $e->getMessage()]);
                $row->update(['syncError' => 'network error']);
            }
        };
        // In a web request the confirmation email / provider call runs after the response has been
        // sent: the visitor never waits for a mail server, and the response time is the same whether
        // or not the address was already subscribed (no enumeration by timing). Elsewhere (console,
        // imports) it runs straight away.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $startOptIn();
        } else {
            defer($startOptIn);
        }
    }

    /** Mark a subscriber confirmed from a signed confirm token. False for invalid tokens. */
    public static function confirm(mixed $token): bool
    {
        $email = self::readToken('confirm', $token);
        if ($email === null) {
            return false;
        }
        $count = NewsletterSubscriber::query()
            ->where('email', $email)
            ->whereIn('status', [NewsletterStatus::PENDING->value, NewsletterStatus::SUBSCRIBED->value])
            ->update(['status' => NewsletterStatus::SUBSCRIBED->value, 'confirmedAt' => Carbon::now('UTC')->format('Y-m-d H:i:s.v'), 'updatedAt' => Carbon::now('UTC')->format('Y-m-d H:i:s.v')]);

        return $count > 0;
    }

    /** Unsubscribe from a signed token (idempotent). False for invalid tokens. */
    public static function unsubscribeWithToken(mixed $token): bool
    {
        $email = self::readToken('unsubscribe', $token);
        if ($email === null) {
            return false;
        }
        self::unsubscribeEmail($email);

        return true;
    }

    public static function unsubscribeEmail(string $email): void
    {
        $now = Carbon::now('UTC')->format('Y-m-d H:i:s.v');
        NewsletterSubscriber::query()->where('email', $email)
            ->update(['status' => NewsletterStatus::UNSUBSCRIBED->value, 'unsubscribedAt' => $now, 'updatedAt' => $now]);
        $provider = Providers::active();
        if ($provider === null) {
            return;
        }
        try {
            $result = Providers::unsubscribe($provider, $email);
            if (! $result['ok']) {
                NewsletterSubscriber::query()->where('email', $email)->update(['syncError' => mb_substr($result['error'], 0, 500)]);
            }
        } catch (Throwable $e) {
            Log::error('[newsletter] provider unsubscribe failed', ['error' => $e->getMessage()]);
        }
    }
}
