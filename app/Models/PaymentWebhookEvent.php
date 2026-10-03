<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationProvider;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `payment_webhook_events` — generated from prisma/schema.prisma (model PaymentWebhookEvent).
 *
 * @property string $id
 * @property DonationProvider $provider
 * @property string $eventId
 * @property string $type
 * @property Carbon|null $processedAt
 * @property string|null $error
 * @property Carbon $receivedAt
 */
class PaymentWebhookEvent extends Model
{
    use HasCuid;

    protected $table = 'payment_webhook_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => DonationProvider::class,
            'processedAt' => 'datetime',
            'receivedAt' => 'datetime',
        ];
    }
}
