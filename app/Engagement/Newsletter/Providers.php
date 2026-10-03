<?php

declare(strict_types=1);

namespace App\Engagement\Newsletter;

use App\Engagement\Mail\Mailer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Newsletter provider adapters (port of lib/newsletter/providers.ts; ADR-008). All optional: with no
 * provider configured, subscribers are stored locally and can be exported from the admin.
 * - brevo: POST /v3/contacts/doubleOptinConfirmation (Brevo sends the DOI mail, then redirects to our confirm URL)
 * - mailchimp: PUT list member with status_if_new "pending" (Mailchimp sends its own confirmation)
 * - smtp: built-in double opt-in over a cPanel mailbox; the list lives in the CMS
 */
final class Providers
{
    private const CONFIRM_COPY = [
        'it' => [
            'subject' => 'Conferma la tua iscrizione alla newsletter',
            'body' => "Grazie per esserti iscritto/a alla newsletter di Sakila per il Nepal.\n\nPer confermare l'iscrizione apri questo link:\n%s\n\nSe non hai richiesto l'iscrizione, ignora questa email: non riceverai altri messaggi.\n",
        ],
        'en' => [
            'subject' => 'Please confirm your newsletter subscription',
            'body' => "Thank you for subscribing to the Sakila per il Nepal newsletter.\n\nTo confirm your subscription, open this link:\n%s\n\nIf you did not ask to subscribe, ignore this email: you won't hear from us again.\n",
        ],
    ];

    /** Active provider name, or null when none is configured (local storage only). */
    public static function active(): ?string
    {
        $wanted = (string) config('engagement.newsletter.provider');
        $c = config('engagement.newsletter');
        if ($wanted === 'brevo' && $c['brevo']['api_key'] && $c['brevo']['list_id'] && $c['brevo']['doi_template_id']) {
            return 'brevo';
        }
        if ($wanted === 'mailchimp' && $c['mailchimp']['api_key'] && $c['mailchimp']['server_prefix'] && $c['mailchimp']['audience_id']) {
            return 'mailchimp';
        }
        if (($wanted === 'smtp' || $wanted === '') && Mailer::isConfigured()) {
            return 'smtp';
        }

        return null;
    }

    public static function isConfigured(): bool
    {
        return self::active() !== null;
    }

    /** @return array{ok: true, providerId?: string}|array{ok: false, error: string} */
    public static function subscribe(string $provider, string $email, string $locale, string $confirmUrl): array
    {
        return match ($provider) {
            'brevo' => self::brevoSubscribe($email, $locale, $confirmUrl),
            'mailchimp' => self::mailchimpSubscribe($email, $locale),
            'smtp' => self::smtpSubscribe($email, $locale, $confirmUrl),
            default => ['ok' => false, 'error' => 'unknown provider'],
        };
    }

    /** @return array{ok: true}|array{ok: false, error: string} */
    public static function unsubscribe(string $provider, string $email): array
    {
        if ($provider === 'brevo') {
            $c = config('engagement.newsletter.brevo');
            $res = Http::withHeaders(['api-key' => $c['api_key']])->timeout(10)
                ->post('https://api.brevo.com/v3/contacts/lists/'.rawurlencode((string) $c['list_id']).'/contacts/remove', ['emails' => [$email]]);

            // 400 "contact already removed / not in list" is fine for our purpose.
            return $res->successful() || in_array($res->status(), [400, 404], true) ? ['ok' => true] : ['ok' => false, 'error' => 'brevo '.$res->status()];
        }
        if ($provider === 'mailchimp') {
            $res = self::mailchimp()->patch(self::mailchimpMemberUrl($email), ['status' => 'unsubscribed']);

            return $res->successful() || $res->status() === 404 ? ['ok' => true] : ['ok' => false, 'error' => 'mailchimp '.$res->status()];
        }

        return ['ok' => true]; // smtp: the CMS row is the list
    }

    /** @return array{ok: true}|array{ok: false, error: string} */
    private static function brevoSubscribe(string $email, string $locale, string $confirmUrl): array
    {
        $c = config('engagement.newsletter.brevo');
        $res = Http::withHeaders(['api-key' => $c['api_key'], 'accept' => 'application/json'])->timeout(10)
            ->post('https://api.brevo.com/v3/contacts/doubleOptinConfirmation', [
                'email' => $email,
                'includeListIds' => [(int) $c['list_id']],
                'templateId' => (int) $c['doi_template_id'],
                'redirectionUrl' => $confirmUrl,
                'attributes' => ['LANGUAGE' => strtoupper($locale)],
            ]);

        // 201 created, 204 already exists/updated
        return in_array($res->status(), [201, 204], true) ? ['ok' => true] : ['ok' => false, 'error' => 'brevo '.$res->status()];
    }

    /** @return array{ok: true, providerId: string}|array{ok: false, error: string} */
    private static function mailchimpSubscribe(string $email, string $locale): array
    {
        $res = self::mailchimp()->put(self::mailchimpMemberUrl($email), [
            'email_address' => $email,
            'status_if_new' => 'pending',
            'language' => $locale,
        ]);

        return $res->successful() ? ['ok' => true, 'providerId' => md5(strtolower($email))] : ['ok' => false, 'error' => 'mailchimp '.$res->status()];
    }

    /** @return array{ok: true}|array{ok: false, error: string} */
    private static function smtpSubscribe(string $email, string $locale, string $confirmUrl): array
    {
        $copy = self::CONFIRM_COPY[$locale] ?? self::CONFIRM_COPY['it'];
        $sent = Mailer::send($email, $copy['subject'], sprintf($copy['body'], $confirmUrl));

        return $sent ? ['ok' => true] : ['ok' => false, 'error' => 'smtp not configured'];
    }

    private static function mailchimp(): PendingRequest
    {
        return Http::withBasicAuth('any', (string) config('engagement.newsletter.mailchimp.api_key'))->timeout(10)->asJson();
    }

    private static function mailchimpMemberUrl(string $email): string
    {
        $c = config('engagement.newsletter.mailchimp');

        return "https://{$c['server_prefix']}.api.mailchimp.com/3.0/lists/{$c['audience_id']}/members/".md5(strtolower($email));
    }
}
