<?php

declare(strict_types=1);

namespace App\Engagement\Validation;

/**
 * Tiny port of the Zod v4 semantics the public form / donation schemas rely on, so the PHP API
 * returns exactly the reference's `{ fieldErrors }` (verified against golden fixtures generated
 * from the real Zod schemas: tests/Fixtures/engagement/validation-golden.json).
 *
 * Rules mirrored from Zod v4:
 * - Type mismatches / enum / literal failures are ABORTING issues ("Invalid input: expected …").
 * - String checks (min/max/regex/refine) are continuable: every failing check is reported, in order.
 * - Lengths count Unicode code points (Zod's `codePointLength`).
 * - Object-level refinements (superRefine) run only when no aborting issue was raised.
 * - Unknown keys are stripped; `undefined` (missing) values are omitted from the output.
 */
final class Parse
{
    /** Marker for "no value" (JS undefined). */
    public const UNDEFINED = "\0undefined\0";

    /** @var array<string, list<string>> */
    public array $fieldErrors = [];

    public bool $aborted = false;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @param  array<string, mixed>  $input */
    public function __construct(public readonly array $input) {}

    public function ok(): bool
    {
        return $this->fieldErrors === [];
    }

    public function issue(string $path, string $message, bool $abort = false): void
    {
        $this->fieldErrors[$path][] = $message;
        if ($abort) {
            $this->aborted = true;
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->input);
    }

    /** Store an output value unless it is undefined. */
    public function set(string $key, mixed $value): void
    {
        if ($value !== self::UNDEFINED) {
            $this->data[$key] = $value;
        }
    }

    // ── value helpers ────────────────────────────────────────────────────────────────────

    /** JS-style type name used in Zod's default messages. */
    public static function typeName(mixed $value, bool $present = true): string
    {
        return match (true) {
            ! $present => 'undefined',
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => is_float($value) && is_nan($value) ? 'NaN' : 'number',
            is_string($value) => 'string',
            is_array($value) && array_is_list($value) && $value !== [] => 'array',
            default => 'object',
        };
    }

    public static function expected(string $type, mixed $value, bool $present = true): string
    {
        return "Invalid input: expected {$type}, received ".self::typeName($value, $present);
    }

    /** String.prototype.trim(): strips Unicode whitespace + line terminators + BOM. */
    public static function trim(string $value): string
    {
        return (string) preg_replace('/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u', '', $value);
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    // ── field parsers (each records issues and returns the parsed value or UNDEFINED) ─────

    /**
     * z.string().trim().max(max, maxMsg)[.min(1, minMsg)] — with optional() support.
     *
     * @param  array{max: int, maxMsg?: string, min1?: string|null, optional?: bool, emptyToUndefined?: bool, regex?: array{0: string, 1: string}|null}  $o
     */
    public function text(string $key, array $o): mixed
    {
        $present = $this->has($key);
        $value = $this->input[$key] ?? null;
        if (! $present && ($o['optional'] ?? false)) {
            return self::UNDEFINED;
        }
        if (! is_string($value)) {
            $this->issue($key, self::expected('string', $value, $present), true);

            return self::UNDEFINED;
        }
        $v = self::trim($value);
        $len = self::length($v);
        if ($len > $o['max']) {
            $this->issue($key, $o['maxMsg'] ?? "Too big: expected string to have <={$o['max']} characters");
        }
        if (isset($o['min1']) && $len < 1) {
            $this->issue($key, $o['min1']);
        }
        if (isset($o['regex']) && ! preg_match($o['regex'][0], $v)) {
            $this->issue($key, $o['regex'][1]);
        }
        if (($o['emptyToUndefined'] ?? false) && $v === '') {
            return self::UNDEFINED;
        }

        return $v;
    }

    /** Zod v4's default email regex (z.email()). */
    public const EMAIL_RE = "/^(?:[A-Za-z0-9_'+\\-]+\\.)*[A-Za-z0-9_'+\\-]*[A-Za-z0-9_+-]@(?:[A-Za-z0-9][A-Za-z0-9\\-]*\\.)+[A-Za-z]{2,}$/";

    /** emailField: trim → min(1,"required") → max(254,"tooLong") → pipe(email "invalidEmail") → lowercase. */
    public function email(string $key): mixed
    {
        $present = $this->has($key);
        $value = $this->input[$key] ?? null;
        if (! is_string($value)) {
            $this->issue($key, self::expected('string', $value, $present), true);

            return self::UNDEFINED;
        }
        $v = self::trim($value);
        $len = self::length($v);
        $failed = false;
        if ($len < 1) {
            $this->issue($key, 'required');
            $failed = true;
        }
        if ($len > 254) {
            $this->issue($key, 'tooLong');
            $failed = true;
        }
        if ($failed) {
            return self::UNDEFINED;
        }
        if (! preg_match(self::EMAIL_RE, $v)) {
            $this->issue($key, 'invalidEmail');

            return self::UNDEFINED;
        }

        return strtolower($v);
    }

    /** z.literal(true, { error: msg }). */
    public function literalTrue(string $key, string $msg): mixed
    {
        if (($this->input[$key] ?? null) !== true) {
            $this->issue($key, $msg, true);

            return self::UNDEFINED;
        }

        return true;
    }

    /**
     * z.enum(values[, { error }]) — optional and "" → undefined when $emptyIsUndefined (optionalEnum()).
     *
     * @param  list<string>  $values
     */
    public function enum(string $key, array $values, ?string $error = null, bool $optional = false, bool $emptyIsUndefined = false): mixed
    {
        $present = $this->has($key);
        $value = $this->input[$key] ?? null;
        if ($emptyIsUndefined && $value === '') {
            $present = false;
        }
        if (! $present && $optional) {
            return self::UNDEFINED;
        }
        if (! is_string($value) || ! in_array($value, $values, true)) {
            $this->issue($key, $error ?? 'Invalid option: expected one of '.implode('|', array_map(fn ($v) => '"'.$v.'"', $values)), true);

            return self::UNDEFINED;
        }

        return $value;
    }

    /** z.boolean().default(d). */
    public function boolean(string $key, bool $default): mixed
    {
        if (! $this->has($key)) {
            return $default;
        }
        $value = $this->input[$key];
        if (! is_bool($value)) {
            $this->issue($key, self::expected('boolean', $value), true);

            return self::UNDEFINED;
        }

        return $value;
    }
}
