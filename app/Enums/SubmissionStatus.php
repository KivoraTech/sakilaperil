<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum SubmissionStatus). */
enum SubmissionStatus: string
{
    case NEW = 'NEW';
    case IN_REVIEW = 'IN_REVIEW';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case ARCHIVED = 'ARCHIVED';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
