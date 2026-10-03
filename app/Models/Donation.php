<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationProvider;
use App\Enums\DonationStatus;
use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `donations` — generated from prisma/schema.prisma (model Donation).
 *
 * @property string $id
 * @property string $reference
 * @property DonationProvider $provider
 * @property DonationStatus $status
 * @property int $amount
 * @property string $currency
 * @property string|null $donorName
 * @property string|null $donorEmail
 * @property string|null $message
 * @property bool $isAnonymous
 * @property Locale $locale
 * @property string|null $projectId
 * @property string|null $campaignId
 * @property string|null $providerReference
 * @property mixed $providerData
 * @property bool $isTestMode
 * @property Carbon|null $completedAt
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Donation extends Model
{
    use HasCuid;

    protected $table = 'donations';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provider' => DonationProvider::class,
            'status' => DonationStatus::class,
            'amount' => 'integer',
            'isAnonymous' => 'boolean',
            'locale' => Locale::class,
            'providerData' => 'array',
            'isTestMode' => 'boolean',
            'completedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectId', 'id');
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaignId', 'id');
    }
}
