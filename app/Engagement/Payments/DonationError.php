<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use RuntimeException;

/** NOT_CONFIGURED | PROVIDER_ERROR | VALIDATION */
final class DonationError extends RuntimeException
{
    public function __construct(public readonly string $codeName)
    {
        parent::__construct($codeName);
    }
}
