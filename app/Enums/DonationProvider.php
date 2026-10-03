<?php

declare(strict_types=1);

namespace App\Enums;

/** Generated from prisma/schema.prisma (enum DonationProvider). */
enum DonationProvider: string
{
    case STRIPE = 'STRIPE';
    case PAYPAL = 'PAYPAL';
    case ESEWA = 'ESEWA';
    case KHALTI = 'KHALTI';
    case BANK_TRANSFER = 'BANK_TRANSFER';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
