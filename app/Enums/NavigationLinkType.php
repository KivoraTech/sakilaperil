<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum NavigationLinkType). */
enum NavigationLinkType: string
{
    case PAGE = 'PAGE';
    case ROUTE = 'ROUTE';
    case INTERNAL = 'INTERNAL';
    case EXTERNAL = 'EXTERNAL';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
