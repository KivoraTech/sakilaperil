<?php

declare(strict_types=1);

namespace App\Engagement\Forms;

use App\Engagement\Validation\Parse;

/**
 * Server-side twins of resources/js/lib/forms/schemas.ts (the reference's lib/forms/schemas.ts).
 * Error messages are i18n KEYS under `forms.errors.*`. Golden-tested against Zod.
 */
final class FormSchemas
{
    public const TYPES = ['contact', 'volunteer', 'membership', 'partner'];

    public const CONTACT_SUBJECTS = ['info', 'donations', 'volunteering', 'partnership', 'press', 'other'];

    public const MEMBERSHIP_TYPES = ['ordinary', 'supporting'];

    public const PARTNERSHIP_TYPES = ['restaurant', 'company', 'school', 'association', 'other'];

    private const PHONE_RE = '/^[+()\d\s.\/-]*$/';

    public static function isFormType(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::TYPES, true);
    }

    /** @param  array<string, mixed>  $input */
    public static function parse(string $type, array $input): Parse
    {
        return match ($type) {
            'contact' => self::contact($input),
            'volunteer' => self::volunteer($input),
            'membership' => self::membership($input),
            'partner' => self::partner($input),
            'newsletter' => self::newsletter($input),
            'spamMeta' => self::spamMeta($input),
            default => throw new \InvalidArgumentException("Unknown form type {$type}"),
        };
    }

    /** @param  array<string, mixed>  $input */
    public static function contact(array $input): Parse
    {
        $p = new Parse($input);
        $p->set('name', self::required($p, 'name', 120));
        $p->set('email', $p->email('email'));
        $p->set('phone', self::phone($p));
        $p->set('subject', $p->enum('subject', self::CONTACT_SUBJECTS, 'invalid', optional: true, emptyIsUndefined: true));
        $p->set('message', self::required($p, 'message', 5000));
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        return $p;
    }

    /** @param  array<string, mixed>  $input */
    public static function volunteer(array $input): Parse
    {
        $p = new Parse($input);
        $p->set('firstName', self::required($p, 'firstName', 80));
        $p->set('lastName', self::required($p, 'lastName', 80));
        $p->set('email', $p->email('email'));
        $p->set('phone', self::phone($p));
        $p->set('country', self::optional($p, 'country', 80));
        $p->set('city', self::optional($p, 'city', 120));
        $p->set('skills', self::optional($p, 'skills', 1000));
        $p->set('availability', self::optional($p, 'availability', 500));
        $p->set('message', self::optional($p, 'message', 3000));
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        return $p;
    }

    /** @param  array<string, mixed>  $input */
    public static function membership(array $input): Parse
    {
        $p = new Parse($input);
        $p->set('firstName', self::required($p, 'firstName', 80));
        $p->set('lastName', self::required($p, 'lastName', 80));
        $p->set('email', $p->email('email'));
        $p->set('phone', self::phone($p));
        $p->set('address', self::optional($p, 'address', 300));
        $p->set('city', self::optional($p, 'city', 120));
        $p->set('country', self::optional($p, 'country', 80));
        $p->set('membershipType', $p->enum('membershipType', self::MEMBERSHIP_TYPES, 'required'));
        $p->set('message', self::optional($p, 'message', 3000));
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        return $p;
    }

    /** @param  array<string, mixed>  $input */
    public static function partner(array $input): Parse
    {
        $p = new Parse($input);
        $p->set('organization', self::required($p, 'organization', 200));
        $p->set('contactName', self::required($p, 'contactName', 120));
        $p->set('email', $p->email('email'));
        $p->set('phone', self::phone($p));
        // text(500).refine(v === "" || /^https?:\/\/[^\s]+\.[^\s]+$/i, "invalidUrl").optional() → "" = undefined
        $website = $p->text('website', ['max' => 500, 'maxMsg' => 'tooLong', 'optional' => true]);
        if ($website !== Parse::UNDEFINED && is_string($website) && $website !== '' && ! preg_match('/^https?:\/\/[^\s]+\.[^\s]+$/i', $website)) {
            $p->issue('website', 'invalidUrl');
        }
        $p->set('website', $website === '' ? Parse::UNDEFINED : $website);
        $p->set('partnershipType', $p->enum('partnershipType', self::PARTNERSHIP_TYPES, 'invalid', optional: true, emptyIsUndefined: true));
        $p->set('message', self::required($p, 'message', 5000));
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        return $p;
    }

    /** @param  array<string, mixed>  $input */
    public static function newsletter(array $input): Parse
    {
        $p = new Parse($input);
        $p->set('email', $p->email('email'));
        $p->set('consent', $p->literalTrue('consent', 'consentRequired'));

        return $p;
    }

    /**
     * Anti-spam metadata posted with every public form (validated, never stored).
     * hp_extra ≤500, startedAt int ≥0, turnstileToken ≤4096, source trimmed ≤60 /^[a-z0-9:_-]*$/i
     * ("footer", "donate-page", "block:<sectionId>").
     *
     * @param  array<string, mixed>  $input
     */
    public static function spamMeta(array $input): Parse
    {
        $p = new Parse($input);
        foreach (['hp_extra' => 500, 'turnstileToken' => 4096] as $key => $max) {
            if (! $p->has($key)) {
                continue;
            }
            $v = $input[$key];
            if (! is_string($v)) {
                $p->issue($key, Parse::expected('string', $v), true);

                continue;
            }
            if (Parse::length($v) > $max) {
                $p->issue($key, "Too big: expected string to have <={$max} characters");
            }
            $p->set($key, $v);
        }
        if ($p->has('startedAt')) {
            $v = $input['startedAt'];
            if (is_float($v) && floor($v) === $v && abs($v) < 2 ** 53) {
                $v = (int) $v;
            }
            if (is_int($v)) {
                if ($v < 0) {
                    $p->issue('startedAt', 'Too small: expected number to be >=0');
                }
                $p->set('startedAt', $v);
            } elseif (is_float($v) && ! is_nan($v)) {
                $p->issue('startedAt', 'Invalid input: expected int, received number', true);
            } else {
                $p->issue('startedAt', Parse::expected('number', $v), true);
            }
        }
        $p->set('source', $p->text('source', [
            'max' => 60,
            'optional' => true,
            'regex' => ['/^[a-z0-9:_-]*$/i', 'Invalid string: must match pattern /^[a-z0-9:_-]*$/i'],
        ]));

        return $p;
    }

    private static function required(Parse $p, string $key, int $max): mixed
    {
        return $p->text($key, ['max' => $max, 'maxMsg' => 'tooLong', 'min1' => 'required']);
    }

    private static function optional(Parse $p, string $key, int $max): mixed
    {
        return $p->text($key, ['max' => $max, 'maxMsg' => 'tooLong', 'optional' => true, 'emptyToUndefined' => true]);
    }

    private static function phone(Parse $p): mixed
    {
        return $p->text('phone', [
            'max' => 40,
            'maxMsg' => 'tooLong',
            'optional' => true,
            'emptyToUndefined' => true,
            'regex' => [self::PHONE_RE, 'invalid'],
        ]);
    }
}
