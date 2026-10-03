<?php

declare(strict_types=1);

namespace App\Pages;

/** A value already encoded as JSON for a raw query-builder write (keeps `{}` vs `[]`). */
final class JsonEncoded
{
    private function __construct(public readonly string $json) {}

    public static function of(mixed $value): self
    {
        return new self((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
