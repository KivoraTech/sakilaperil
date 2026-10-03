<?php

declare(strict_types=1);

namespace App\Engagement\Payments;

use RuntimeException;

final class ProviderError extends RuntimeException
{
    public function __construct(public readonly string $provider, string $message)
    {
        parent::__construct("[{$provider}] {$message}");
    }
}
