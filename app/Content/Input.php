<?php

declare(strict_types=1);

namespace App\Content;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Tiny Zod-like reader for action input. Mirrors the semantics of the reference's Zod schemas
 * (resources/js/lib/content/*.ts, which also run in the browser): strings are trimmed then
 * length-checked, wrong types are errors, field errors are keyed by the SAME dotted paths React
 * Hook Form uses ("translations.it.slug", "items.3.mediaAssetId"). Collect, then ->validate().
 */
final class Input
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public static function from(mixed $data): self
    {
        return new self(is_array($data) ? $data : []);
    }

    public function raw(string $path): mixed
    {
        return Arr::get($this->data, $path);
    }

    public function has(string $path): bool
    {
        return Arr::has($this->data, $path);
    }

    public function error(string $path, string $message): void
    {
        $this->errors[$path] ??= [];
        $this->errors[$path][] = $message;
    }

    public function hasError(string $path): bool
    {
        return isset($this->errors[$path]);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** Throw a ValidationException (→ VALIDATION ActionResult with fieldErrors) when anything failed. */
    public function validate(): void
    {
        if ($this->errors !== []) {
            throw ValidationException::withMessages($this->errors);
        }
    }

    /** z.string().trim().max(max) (+ min(1) when required). */
    public function str(string $path, int $max, bool $required = false, ?string $requiredMessage = null): string
    {
        $v = $this->raw($path);
        if (! is_string($v)) {
            $this->error($path, 'Invalid input: expected string');

            return '';
        }
        $v = self::trim($v);
        if (mb_strlen($v) > $max) {
            $this->error($path, "Too big: expected string to have <={$max} characters");
        }
        if ($required && $v === '') {
            $this->error($path, $requiredMessage ?? 'Required');
        }

        return $v;
    }

    /** z.string().trim().max(max).nullable() — null stays null. */
    public function strOrNull(string $path, int $max): ?string
    {
        return $this->raw($path) === null ? null : $this->str($path, $max);
    }

    public function bool(string $path): bool
    {
        $v = $this->raw($path);
        if (! is_bool($v)) {
            $this->error($path, 'Invalid input: expected boolean');

            return false;
        }

        return $v;
    }

    /** z.coerce.number().int().min().max() */
    public function int(string $path, int $min, int $max): int
    {
        $v = $this->raw($path);
        if (is_string($v) && trim($v) !== '' && is_numeric(trim($v))) {
            $v = trim($v) + 0;
        }
        if (is_bool($v)) {
            $v = (int) $v;
        }
        if ($v === null || $v === '') {
            $v = 0; // Number(null) / Number("") === 0
        }
        if (is_float($v) && (is_nan($v) || is_infinite($v) || abs($v) > 9.0e15)) {
            // Beyond the safe integer range: casting would warn (→ 500) and the value is out of bounds anyway.
            $this->error($path, $v < 0 ? "Too small: expected number to be >={$min}" : "Too big: expected number to be <={$max}");

            return $min;
        }
        if (is_float($v) && floor($v) === $v) {
            $v = (int) $v;
        }
        if (! is_int($v)) {
            $this->error($path, 'Invalid input: expected int');

            return $min;
        }
        if ($v < $min) {
            $this->error($path, "Too small: expected number to be >={$min}");
        }
        if ($v > $max) {
            $this->error($path, "Too big: expected number to be <={$max}");
        }

        return $v;
    }

    /** @param list<string> $values */
    public function enum(string $path, array $values): string
    {
        $v = $this->raw($path);
        if (! is_string($v) || ! in_array($v, $values, true)) {
            $this->error($path, 'Invalid option: expected one of '.implode('|', array_map(fn ($x) => "\"{$x}\"", $values)));

            return $values[0];
        }

        return $v;
    }

    /** @param list<string> $values */
    public function enumOrNull(string $path, array $values): ?string
    {
        return $this->raw($path) === null ? null : $this->enum($path, $values);
    }

    /** idSchema: z.string().trim().min(1).max(64) */
    public function id(string $path): string
    {
        $v = $this->str($path, 64);
        if ($v === '' && ! $this->hasError($path)) {
            $this->error($path, 'Too small: expected string to have >=1 characters');
        }

        return $v;
    }

    /** optionalId: z.string().trim().max(64).nullable() — "" is kept as "" by Zod; we normalise to null. */
    public function optionalId(string $path): ?string
    {
        $v = $this->raw($path);
        if ($v === null || ! $this->has($path)) {
            if (! $this->has($path)) {
                $this->error($path, 'Invalid input: expected string, received undefined');
            }

            return null;
        }
        $s = $this->str($path, 64);

        return $s === '' ? null : $s;
    }

    /** isoDateOrEmpty: "" or an ISO datetime with offset. */
    public function isoOrEmpty(string $path, ?string $message = null): string
    {
        $v = $this->raw($path);
        if (! is_string($v)) {
            $this->error($path, $message ?? 'Invalid input');

            return '';
        }
        if ($v === '') {
            return '';
        }
        if (! self::isIsoDateTime($v)) {
            $this->error($path, $message ?? 'Invalid ISO datetime');

            return '';
        }

        return $v;
    }

    /** z.iso.datetime({ offset: true }) (required). */
    public function iso(string $path, string $message): string
    {
        $v = $this->raw($path);
        if (! is_string($v) || ! self::isIsoDateTime($v)) {
            $this->error($path, $message);

            return '';
        }

        return $v;
    }

    /** httpsUrlOrEmpty */
    public function httpsUrlOrEmpty(string $path): string
    {
        $v = $this->str($path, 500);
        if ($v !== '' && preg_match('~^https://[^\s]+$~Du', $v) !== 1) {
            $this->error($path, 'Use a full https:// link');
        }

        return $v;
    }

    /**
     * Structural rich-text check (richTextDocSchema): { type: "doc", content: node[] ≤ 2000 }.
     * The strict allow-list pass (RichText::sanitize) runs afterwards.
     *
     * @return array<string, mixed>
     */
    public function doc(string $path): array
    {
        $v = $this->raw($path);
        if (! is_array($v) || ($v['type'] ?? null) !== 'doc') {
            $this->error($path, 'Invalid input');

            return ['type' => 'doc', 'content' => []];
        }
        $content = $v['content'] ?? [];
        if (! is_array($content) || ! array_is_list($content) || count($content) > 2000) {
            $this->error($path, 'Invalid input');

            return ['type' => 'doc', 'content' => []];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /** @return list<mixed> */
    public function list(string $path, int $max): array
    {
        $v = $this->raw($path);
        if (! is_array($v) || ! array_is_list($v)) {
            $this->error($path, 'Invalid input: expected array');

            return [];
        }
        if (count($v) > $max) {
            $this->error($path, "Too big: expected array to have <={$max} items");
        }

        return $v;
    }

    /** ISO 8601 datetime with "Z" or ±hh:mm offset (what Zod's z.iso.datetime({offset:true}) accepts). */
    public static function isIsoDateTime(string $v): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/D', $v) === 1
            && strtotime($v) !== false;
    }

    /** String.prototype.trim (Unicode whitespace). */
    public static function trim(string $s): string
    {
        return (string) preg_replace('/^[\s\x{FEFF}\x{00A0}]+|[\s\x{FEFF}\x{00A0}]+$/u', '', $s);
    }
}
