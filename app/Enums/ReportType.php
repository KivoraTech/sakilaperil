<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum ReportType). */
enum ReportType: string
{
    case ANNUAL_REPORT = 'ANNUAL_REPORT';
    case FINANCIAL_STATEMENT = 'FINANCIAL_STATEMENT';
    case SOCIAL_REPORT = 'SOCIAL_REPORT';
    case OTHER = 'OTHER';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
