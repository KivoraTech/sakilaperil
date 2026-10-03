<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum ContactStatus). */
enum ContactStatus: string
{
    case NEW = 'NEW';
    case READ = 'READ';
    case REPLIED = 'REPLIED';
    case SPAM = 'SPAM';
    case ARCHIVED = 'ARCHIVED';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
