<?php

declare(strict_types=1);

namespace App\I18n;

/**
 * next-intl compatible dictionary lookups over resources/messages/{it,en}.json (the SAME files as
 * the reference app). `t('donate.title')`, `t('projects.count', ['count' => 3])`. A missing key
 * returns the key path (next-intl behaviour), so gaps are visible, never fatal.
 */
final class Translator
{
    /** @var array<string, array<string, mixed>> */
    private static array $dictionaries = [];

    /** @param array<string, mixed> $params */
    public static function t(string $key, array $params = [], ?string $locale = null): string
    {
        $message = self::raw($key, $locale);

        return is_string($message) ? MessageFormatter::format($message, $params) : $key;
    }

    public static function has(string $key, ?string $locale = null): bool
    {
        return is_string(self::raw($key, $locale));
    }

    /** Raw value (string or nested namespace array) for a key, or null. */
    public static function raw(string $key, ?string $locale = null): mixed
    {
        $node = self::dictionary($locale ?? Locales::current());
        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }
            $node = $node[$segment];
        }

        return $node;
    }

    /** @return array<string, mixed> */
    public static function dictionary(string $locale): array
    {
        if (! Locales::isLocale($locale)) {
            $locale = Locales::DEFAULT;
        }
        if (! isset(self::$dictionaries[$locale])) {
            $json = file_get_contents(resource_path("messages/{$locale}.json"));
            $data = $json !== false ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : [];
            self::$dictionaries[$locale] = is_array($data) ? $data : [];
        }

        return self::$dictionaries[$locale];
    }
}
