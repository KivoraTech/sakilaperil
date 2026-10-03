<?php

declare(strict_types=1);

namespace App\Settings;

use stdClass;

/**
 * Rules of the Settings screen that JSON Schema cannot express (the `superRefine` parts of
 * resources/js/lib/settings/sections.ts and the format checks in lib/settings/rules.ts). Applied when
 * a section is SAVED; stored "[[TODO: …]]" placeholders and blanks are allowed. Keep in sync with TS.
 */
final class SettingsRules
{
    public const MESSAGES = [
        'email' => 'Enter a valid email address, like info@example.org',
        'phone' => 'Enter a phone number with digits only (spaces, + and - are fine), like +39 02 1234 5678',
        'iban' => 'This IBAN is not valid. Check it against the bank statement: one wrong character sends donations nowhere',
        'bic' => 'A BIC/SWIFT code has 8 or 11 letters and digits, like ABCDITMM',
    ];

    /**
     * @return array<string, list<string>> field path (dot notation) → messages
     */
    public static function check(string $section, stdClass $values): array
    {
        $errors = [];
        if ($section === 'contact' && ($values->contact ?? null) instanceof stdClass) {
            $c = $values->contact;
            if (self::filled($c->email ?? null) && ! self::isValidEmail((string) $c->email)) {
                $errors['contact.email'] = [self::MESSAGES['email']];
            }
            foreach (['phone', 'whatsapp'] as $key) {
                if (self::filled($c->{$key} ?? null) && ! self::isValidPhone((string) $c->{$key})) {
                    $errors["contact.{$key}"] = [self::MESSAGES['phone']];
                }
            }
        }
        if ($section === 'donations') {
            $b = $values->bankDetails ?? null;
            if ($b instanceof stdClass) {
                if (self::filled($b->iban ?? null) && ! self::isValidIban((string) $b->iban)) {
                    $errors['bankDetails.iban'] = [self::MESSAGES['iban']];
                }
                if (self::filled($b->bic ?? null) && ! self::isValidBic((string) $b->bic)) {
                    $errors['bankDetails.bic'] = [self::MESSAGES['bic']];
                }
            }
            $d = $values->donation ?? null;
            if ($d instanceof stdClass && is_array($d->presetAmounts ?? null) && ! in_array($d->defaultAmount ?? null, $d->presetAmounts, true)) {
                $errors['donation.defaultAmount'] = ['The default amount must be one of the suggested amounts'];
            }
        }
        if ($section === 'impact' && is_array($values->impactStats ?? null)) {
            $seen = [];
            foreach ($values->impactStats as $i => $stat) {
                $key = $stat instanceof stdClass ? ($stat->key ?? null) : null;
                if (is_string($key) && isset($seen[$key])) {
                    $errors["impactStats.{$i}.key"] = ['Keys must be unique'];
                }
                if (is_string($key)) {
                    $seen[$key] = true;
                }
            }
        }

        return $errors;
    }

    private static function filled(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '' && ! SettingsTodo::isTodo($v);
    }

    public static function isValidEmail(string $v): bool
    {
        return mb_strlen($v) <= 200 && preg_match('/^[^\s@<>()\[\],;:"]+@[^\s@<>()\[\],;:"]+\.[A-Za-z]{2,}$/', trim($v)) === 1;
    }

    public static function isValidPhone(string $v): bool
    {
        $t = trim($v);
        $digits = strlen((string) preg_replace('/\D/', '', $t));

        return preg_match('/^\+?[0-9 ().\/-]+$/', $t) === 1 && $digits >= 6 && $digits <= 15;
    }

    /** ISO 13616: country + 2 check digits + BBAN, mod-97 remainder 1. Spaces are ignored. */
    public static function isValidIban(string $v): bool
    {
        $iban = strtoupper((string) preg_replace('/\s+/', '', $v));
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }
        $remainder = 0;
        foreach (str_split(substr($iban, 4).substr($iban, 0, 4)) as $ch) {
            $n = ctype_alpha($ch) ? ord($ch) - 55 : (int) $ch;
            $remainder = ($remainder * ($n > 9 ? 100 : 10) + $n) % 97;
        }

        return $remainder === 1;
    }

    public static function isValidBic(string $v): bool
    {
        return preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', strtoupper((string) preg_replace('/\s+/', '', $v))) === 1;
    }
}
