<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use App\Engagement\Validation\Parse;

/**
 * Port of resources/js/lib/payments/schemas.ts (reference lib/payments/schemas.ts): donation input,
 * amount limits and currencies. Amounts are entered in MAJOR units and stored in minor units.
 */
final class DonationSchema
{
    public const ONLINE_PROVIDERS = ['STRIPE', 'PAYPAL', 'ESEWA', 'KHALTI'];

    public const FREQUENCIES = ['once', 'monthly'];

    /** @var array<string, 'EUR'|'NPR'> */
    public const CURRENCY_FOR = ['STRIPE' => 'EUR', 'PAYPAL' => 'EUR', 'ESEWA' => 'NPR', 'KHALTI' => 'NPR'];

    /** Min/max per currency in major units (Khalti's minimum is Rs 10). */
    public const AMOUNT_LIMITS = ['EUR' => ['min' => 2, 'max' => 10_000], 'NPR' => ['min' => 10, 'max' => 500_000]];

    /** Default NPR presets — `[[TODO: confirm NPR presets with the client]]` (design-system §8.10). */
    public const DEFAULT_NPR_PRESETS = [500, 1000, 2500, 5000];

    public static function toMinor(int|float $major): int
    {
        return (int) round($major * 100);
    }

    /** @param  array<string, mixed>  $input */
    public static function parse(array $input): Parse
    {
        $p = new Parse($input);

        $provider = $p->enum('provider', self::ONLINE_PROVIDERS);
        $p->set('provider', $provider);

        $amount = $input['amount'] ?? null;
        if (! array_key_exists('amount', $input) || ! (is_int($amount) || is_float($amount)) || (is_float($amount) && (is_nan($amount) || is_infinite($amount)))) {
            $p->issue('amount', 'invalidAmount', true);
            $amount = Parse::UNDEFINED;
        } else {
            if ($amount <= 0) {
                $p->issue('amount', 'invalidAmount');
            }
            $p->set('amount', $amount);
        }

        $frequency = $p->has('frequency') ? $p->enum('frequency', self::FREQUENCIES) : 'once';
        $p->set('frequency', $frequency);

        foreach (['projectId', 'campaignId'] as $key) {
            $p->set($key, $p->text($key, ['max' => 64, 'optional' => true]));
        }
        $p->set('donorName', $p->text('donorName', ['max' => 120, 'maxMsg' => 'tooLong', 'optional' => true, 'emptyToUndefined' => true]));
        $donorEmail = self::donorEmail($p);
        $p->set('donorEmail', $donorEmail);
        $p->set('message', $p->text('message', ['max' => 500, 'maxMsg' => 'tooLong', 'optional' => true, 'emptyToUndefined' => true]));
        $p->set('isAnonymous', $p->boolean('isAnonymous', false));
        $newsletter = $p->boolean('newsletter', false);
        $p->set('newsletter', $newsletter);
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        // superRefine: only when the object itself parsed (no aborting issue).
        if (! $p->aborted && $provider !== Parse::UNDEFINED && is_string($provider) && (is_int($amount) || is_float($amount))) {
            $currency = self::CURRENCY_FOR[$provider];
            ['min' => $min, 'max' => $max] = self::AMOUNT_LIMITS[$currency];
            if ($amount < $min || $amount > $max) {
                $p->issue('amount', 'amountOutOfRange');
            }
            if (abs($amount * 100 - round($amount * 100)) > 1e-6) {
                $p->issue('amount', 'invalidAmount');
            }
            if ($frequency === 'monthly' && $provider !== 'STRIPE') {
                $p->issue('frequency', 'monthlyCardOnly');
            }
            if ($newsletter === true && $donorEmail === Parse::UNDEFINED) {
                $p->issue('donorEmail', 'required');
            }
        }

        return $p;
    }

    /** z.string().trim().max(254).optional().transform(v ? lower : undefined).pipe(z.email("invalidEmail").optional()). */
    private static function donorEmail(Parse $p): mixed
    {
        if (! $p->has('donorEmail')) {
            return Parse::UNDEFINED;
        }
        $value = $p->input['donorEmail'];
        if (! is_string($value)) {
            $p->issue('donorEmail', Parse::expected('string', $value), true);

            return Parse::UNDEFINED;
        }
        $v = Parse::trim($value);
        if (Parse::length($v) > 254) {
            $p->issue('donorEmail', 'Too big: expected string to have <=254 characters');

            return Parse::UNDEFINED;
        }
        if ($v === '') {
            return Parse::UNDEFINED;
        }
        $v = strtolower($v);
        if (! preg_match(Parse::EMAIL_RE, $v)) {
            $p->issue('donorEmail', 'invalidEmail');

            return Parse::UNDEFINED;
        }

        return $v;
    }
}
