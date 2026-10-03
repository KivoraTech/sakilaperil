<?php

declare(strict_types=1);

/*
 * Engagement (forms, newsletter, donations, email) — ADR-008.
 * Read from env here so `php artisan config:cache` works on cPanel (never call env() elsewhere).
 * Email itself uses Laravel's mail config (MAIL_MAILER=smtp + MAIL_* from a cPanel mailbox).
 */
return [
    /** Salts IP hashes stored with submissions / rate-limit keys (raw IPs are never stored). */
    'ip_hash_salt' => env('IP_HASH_SALT', env('APP_KEY', 'sakila')),

    /** Team inbox for new submissions/donations (emails carry only a CMS link, no personal data). */
    'notify_email_to' => env('NOTIFY_EMAIL_TO', ''),

    /** Optional Cloudflare Turnstile: active only when BOTH keys are set. */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY', env('NEXT_PUBLIC_TURNSTILE_SITE_KEY', '')),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    /** brevo | mailchimp | smtp | '' (= built-in SMTP double opt-in when mail is configured, else store locally). */
    'newsletter' => [
        'provider' => env('NEWSLETTER_PROVIDER', ''),
        'secret' => env('NEWSLETTER_TOKEN_SECRET', env('APP_KEY', '')),
        'brevo' => [
            'api_key' => env('BREVO_API_KEY', ''),
            'list_id' => env('BREVO_LIST_ID', ''),
            'doi_template_id' => env('BREVO_DOI_TEMPLATE_ID', ''),
        ],
        'mailchimp' => [
            'api_key' => env('MAILCHIMP_API_KEY', ''),
            'server_prefix' => env('MAILCHIMP_SERVER_PREFIX', ''),
            'audience_id' => env('MAILCHIMP_AUDIENCE_ID', ''),
        ],
    ],

    /** Payment providers. Test mode unless explicitly "live" (Stripe: sk_test_/rk_test_ keys). */
    'payments' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY', ''),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID', ''),
            'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
            'env' => env('PAYPAL_ENV', 'sandbox'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),
        ],
        'esewa' => [
            'merchant_code' => env('ESEWA_MERCHANT_CODE', ''),
            'secret_key' => env('ESEWA_SECRET_KEY', ''),
            'env' => env('ESEWA_ENV', 'test'),
        ],
        'khalti' => [
            'secret_key' => env('KHALTI_SECRET_KEY', ''),
            'env' => env('KHALTI_ENV', 'test'),
        ],
    ],
];
