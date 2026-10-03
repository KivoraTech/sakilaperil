<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum NewsletterStatus). */
enum NewsletterStatus: string
{
    case PENDING = 'PENDING';
    case SUBSCRIBED = 'SUBSCRIBED';
    case UNSUBSCRIBED = 'UNSUBSCRIBED';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
