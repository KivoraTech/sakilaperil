<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum ContentStatus). */
enum ContentStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case ARCHIVED = 'ARCHIVED';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
