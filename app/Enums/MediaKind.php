<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum MediaKind). */
enum MediaKind: string
{
    case IMAGE = 'IMAGE';
    case VIDEO = 'VIDEO';
    case DOCUMENT = 'DOCUMENT';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
