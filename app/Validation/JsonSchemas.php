<?php

declare(strict_types=1);

namespace App\Validation;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use RuntimeException;
use stdClass;

/**
 * Server-side validation with the JSON Schemas exported from the admin's Zod schemas
 * (scripts/export-schemas.ts → resources/schemas/*.json), so PHP and the React admin agree (ADR-012).
 *
 * Before validating, values are normalised the way Zod parses them: strings trimmed/lowercased where the
 * Zod schema says so (`x-trim`/`x-lowercase`), unknown object
 * keys stripped, PHP's ambiguous empty array turned into {} or [] according to the schema. Opis then
 * applies `default` values. The result is Zod's OUTPUT shape.
 *
 * Store JSON with `JsonSchemas::encode($result->object)` so empty objects stay `{}` (the reference
 * app's Zod parsers reject `[]` where an object is expected).
 */
final class JsonSchemas
{
    /** @var array<string, array<string, mixed>> */
    private static array $files = [];

    /**
     * @param  string  $file  resources/schemas/<file>.json
     * @param  string  $path  dot path inside the file, e.g. "siteSettingsSchema" or "types.hero.content"
     */
    public static function validate(string $file, string $path, mixed $data): SchemaResult
    {
        $schema = self::schema($file, $path);
        $object = self::normalize($data, $schema, $schema);
        $validator = new Validator;
        $validator->setMaxErrors(50);
        // Validate against the schema decoded as OBJECTS: the assoc copy turns `{}` (e.g. a record's
        // `additionalProperties: {}`) into `[]`, which Opis rejects as an invalid schema.
        $result = $validator->validate($object, self::schemaObject($file, $path));
        if ($result->isValid()) {
            return new SchemaResult(true, $object, []);
        }

        return new SchemaResult(false, $object, self::issues($result->error()));
    }

    /** @return array<string, mixed> */
    public static function schema(string $file, string $path): array
    {
        if (! isset(self::$files[$file])) {
            $json = @file_get_contents(resource_path("schemas/{$file}.json"));
            if ($json === false) {
                throw new RuntimeException("resources/schemas/{$file}.json missing — run `npm run schemas`");
            }
            self::$files[$file] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        $node = self::$files[$file];
        foreach (explode('.', $path) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                throw new RuntimeException("Schema {$file}:{$path} not found");
            }
            $node = $node[$segment];
        }

        /** @var array<string, mixed> $node */
        return $node;
    }

    /** @var array<string, stdClass> */
    private static array $objectFiles = [];

    /** The schema at $path decoded as stdClass (keeps `{}` distinct from `[]`). */
    private static function schemaObject(string $file, string $path): mixed
    {
        if (! isset(self::$objectFiles[$file])) {
            self::schema($file, $path); // validates the path / file existence first
            self::$objectFiles[$file] = json_decode((string) file_get_contents(resource_path("schemas/{$file}.json")), false, 512, JSON_THROW_ON_ERROR);
        }
        $node = self::$objectFiles[$file];
        foreach (explode('.', $path) as $segment) {
            $node = $node->{$segment};
        }

        return $node;
    }

    /** JSON for storage: objects stay objects (`{}`), lists stay lists, unicode unescaped. */
    public static function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** stdClass tree → associative arrays (for PHP code that reads the validated data). */
    public static function toArray(mixed $value): mixed
    {
        return json_decode((string) json_encode($value), true);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $root
     */
    private static function normalize(mixed $value, array $schema, array $root): mixed
    {
        $schema = self::resolve($schema, $root);
        foreach (['anyOf', 'oneOf'] as $combinator) {
            if (isset($schema[$combinator]) && is_array($schema[$combinator])) {
                $schema = self::pickBranch($value, $schema[$combinator], $root) ?? $schema;
                $schema = self::resolve($schema, $root);
            }
        }
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }
        $types = (array) ($schema['type'] ?? []);
        $isObject = in_array('object', $types, true) || isset($schema['properties']);
        $isArray = in_array('array', $types, true) || isset($schema['items']);

        if (is_string($value)) {
            // Only where the Zod schema has .trim() / .toLowerCase() (marked by scripts/export-schemas.ts).
            if (! empty($schema['x-trim'])) {
                $value = trim($value);
            }

            return ! empty($schema['x-lowercase']) ? mb_strtolower($value) : $value;
        }
        if (! is_array($value)) {
            return $value;
        }
        if ($value === []) {
            return $isObject && ! $isArray ? new stdClass : [];
        }
        if (array_is_list($value) && ! $isObject) {
            $items = is_array($schema['items'] ?? null) ? $schema['items'] : [];

            return array_map(fn ($v) => self::normalize($v, $items, $root), $value);
        }
        $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : null;
        $additional = $schema['additionalProperties'] ?? null;
        $out = new stdClass;
        foreach ($value as $key => $v) {
            $key = (string) $key;
            if ($properties !== null && array_key_exists($key, $properties)) {
                $out->{$key} = self::normalize($v, $properties[$key], $root);
            } elseif (is_array($additional)) {
                $out->{$key} = self::normalize($v, $additional, $root);
            } elseif ($properties === null || $additional === true) {
                $out->{$key} = self::normalize($v, [], $root);
            }
            // else: unknown key → stripped, like Zod's default object parsing.
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $branches
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>|null
     */
    private static function pickBranch(mixed $value, array $branches, array $root): ?array
    {
        $kind = match (true) {
            is_string($value) => 'string',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            $value === null => 'null',
            $value instanceof stdClass, is_array($value) && ! array_is_list($value) => 'object',
            is_array($value) && $value === [] => 'empty',
            default => 'array',
        };
        foreach ($branches as $branch) {
            $b = self::resolve($branch, $root);
            $types = (array) ($b['type'] ?? []);
            if ($kind === 'empty' && (in_array('object', $types, true) || in_array('array', $types, true))) {
                return $b;
            }
            if (in_array($kind, $types, true) || ($kind === 'integer' && in_array('number', $types, true))) {
                return $b;
            }
            if ($kind === 'object' && isset($b['properties'])) {
                return $b;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>
     */
    private static function resolve(array $schema, array $root): array
    {
        $guard = 0;
        while (isset($schema['$ref']) && is_string($schema['$ref']) && str_starts_with($schema['$ref'], '#/') && $guard++ < 20) {
            $node = $root;
            foreach (explode('/', substr($schema['$ref'], 2)) as $segment) {
                $node = is_array($node) ? ($node[str_replace(['~1', '~0'], ['/', '~'], $segment)] ?? []) : [];
            }
            $schema = is_array($node) ? $node : [];
        }

        return $schema;
    }

    /**
     * Validation errors as short, human messages (the same wording the client-side Zod schemas show),
     * never the validator library's own text. A missing required property is reported ON that property.
     *
     * @return list<array{path: list<string>, message: string}>
     */
    private static function issues(?ValidationError $error): array
    {
        if ($error === null) {
            return [];
        }
        $out = [];
        $formatter = new ErrorFormatter;
        $pairs = $formatter->format($error, true, fn (ValidationError $e, ?string $message = null): array => [$e, $formatter->formatErrorMessage($e, $message)]);
        foreach ($pairs as $pointer => $errors) {
            $path = array_map('strval', array_values(array_filter(explode('/', (string) $pointer), fn ($s) => $s !== '')));
            foreach ((array) $errors as [$e, $raw]) {
                if ($e->keyword() === 'required') {
                    foreach ((array) ($e->args()['missing'] ?? []) as $property) {
                        $out[] = ['path' => [...$path, (string) $property], 'message' => 'Required'];
                    }

                    continue;
                }
                $out[] = ['path' => $path, 'message' => self::friendly($e, (string) $raw)];
            }
        }

        return $out;
    }

    private static function friendly(ValidationError $e, string $raw): string
    {
        $args = $e->args();
        $n = static fn (string $key): string => is_scalar($args[$key] ?? null) ? (string) $args[$key] : '';

        return match ($e->keyword()) {
            'type' => 'Invalid value',
            'minLength' => (int) $n('min') <= 1 ? 'Required' : 'Too short: use at least '.$n('min').' characters',
            'maxLength' => 'Too long: use at most '.$n('max').' characters',
            'minimum', 'exclusiveMinimum' => 'Must be at least '.$n('min'),
            'maximum', 'exclusiveMaximum' => 'Must be at most '.$n('max'),
            'minItems' => 'Add at least '.$n('min'),
            'maxItems' => 'Too many: keep at most '.$n('max'),
            'enum', 'const' => 'Invalid option',
            'pattern' => ($args['pattern'] ?? null) === '^[a-z0-9]+(?:-[a-z0-9]+)*$'
                ? 'Use lowercase letters, numbers and single dashes (e.g. chi-siamo)'
                : 'Invalid format',
            'format' => 'Invalid format',
            'additionalProperties', 'unevaluatedProperties' => 'Unknown field',
            default => $raw,
        };
    }
}
