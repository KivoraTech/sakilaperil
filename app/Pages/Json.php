<?php

declare(strict_types=1);

namespace App\Pages;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * JSON + date helpers for the page builder. JSON columns are read RAW and decoded as stdClass trees
 * so `{}` survives the round trip to the React admin (PHP's assoc decoding would turn it into `[]`).
 */
final class Json
{
    /** ISO-8601 in UTC with milliseconds, exactly like JavaScript's Date#toISOString(). */
    public static function iso(?DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /** Decode a JSON column of a model without losing `{}` (stdClass for objects). */
    public static function column(Model $model, string $column): mixed
    {
        $raw = $model->getRawOriginal($column);
        if ($raw === null || $raw === '') {
            return null;
        }

        return is_string($raw) ? json_decode($raw, false) : $raw;
    }

    /** A JSON object for the client: stdClass (never a list); anything else becomes {}. */
    public static function object(mixed $value): stdClass
    {
        if ($value instanceof stdClass) {
            return $value;
        }
        if (is_array($value) && ($value === [] || ! array_is_list($value))) {
            return (object) $value;
        }

        return new stdClass;
    }

    /** Decode a request body as stdClass trees (keeps `{}` distinct from `[]`). */
    public static function body(string $content): mixed
    {
        if (trim($content) === '') {
            return null;
        }

        return json_decode($content, false, 512, JSON_BIGINT_AS_STRING);
    }

    /** Byte length of a value encoded like JSON.stringify (for MAX_SECTION_BYTES). */
    public static function length(mixed $value): int
    {
        return strlen((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
