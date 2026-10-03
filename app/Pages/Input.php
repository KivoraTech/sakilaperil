<?php

declare(strict_types=1);

namespace App\Pages;

use App\I18n\Routes;
use App\Validation\JsonSchemas;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Request parsing for the builder actions: structure from the JSON Schemas exported from
 * resources/js/lib/** (same rules as the admin's Zod), plus the Zod transforms/messages that JSON
 * Schema cannot express (slug lowercase/reserved, optional text → null).
 * Validation failures throw ValidationException (→ ActionResult VALIDATION, fieldErrors keyed by
 * the first path segment, like z.flattenError()).
 */
final class Input
{
    public const HOME_SLUG = 'home';

    public static function body(Request $request): mixed
    {
        return Json::body((string) $request->getContent());
    }

    /** Validate against resources/schemas/<file>.json → <path>; returns the normalised stdClass. */
    public static function schema(string $file, string $path, mixed $input): stdClass
    {
        $result = JsonSchemas::validate($file, $path, $input);
        if (! $result->ok || ! $result->object instanceof stdClass) {
            throw self::invalid($result->issues ?: [['path' => [], 'message' => 'Invalid input']]);
        }

        return $result->object;
    }

    /** @param list<array{path: list<string>, message: string}> $issues */
    public static function invalid(array $issues): ValidationException
    {
        $fieldErrors = [];
        foreach ($issues as $issue) {
            $key = (string) ($issue['path'][0] ?? '');
            if ($key === '') {
                $key = '_form';
            }
            $fieldErrors[$key][] = $issue['message'];
        }

        return ValidationException::withMessages($fieldErrors);
    }

    /**
     * slugSchema: trim + lowercase + pattern + reserved + not "home". Returns [slug, error].
     *
     * @return array{0: string, 1: ?string}
     */
    public static function slug(mixed $raw): array
    {
        $slug = mb_strtolower(trim(is_string($raw) ? $raw : ''));
        $error = match (true) {
            $slug === '' => 'Enter a slug',
            mb_strlen($slug) > 80 => 'Keep the slug under 80 characters',
            preg_match(Routes::SLUG_PATTERN, $slug) !== 1 => 'Use lowercase letters, numbers and single dashes (e.g. chi-siamo)',
            in_array($slug, Routes::reservedSlugs(), true) => 'This slug is reserved by the website',
            $slug === self::HOME_SLUG => '“home” is reserved for the home page',
            default => null,
        };

        return [$slug, $error];
    }

    public static function requireSlug(mixed $raw, string $field = 'slug'): string
    {
        [$slug, $error] = self::slug($raw);
        if ($error !== null) {
            throw ValidationException::withMessages([$field => [$error]]);
        }

        return $slug;
    }

    /** z.string().trim().max(n).optional().transform(v => v || null) */
    public static function optionalText(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';

        return $v === '' ? null : $v;
    }

    public static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
