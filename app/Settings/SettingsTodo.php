<?php

declare(strict_types=1);

namespace App\Settings;

/** "[[TODO: ...]]" marks client facts we do not know yet (port of lib/settings/todo.ts). Never invent them. */
final class SettingsTodo
{
    public const PATTERN = '/\[\[TODO:[^\]]*\]\]/';

    public static function isTodo(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /** Returns null for TODO placeholders so the public site can hide the field. */
    public static function known(mixed $value): mixed
    {
        return self::isTodo($value) ? null : $value;
    }
}
