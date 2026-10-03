<?php

declare(strict_types=1);

namespace App\Validation;

/** Outcome of JsonSchemas::validate(): normalised data (stdClass tree, defaults applied) + issues. */
final class SchemaResult
{
    /** @param list<array{path: list<string>, message: string}> $issues */
    public function __construct(
        public readonly bool $ok,
        public readonly mixed $object,
        public readonly array $issues,
    ) {}

    /** The validated data as associative arrays. */
    public function data(): mixed
    {
        return JsonSchemas::toArray($this->object);
    }
}
