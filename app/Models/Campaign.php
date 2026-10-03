<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `campaigns` — generated from prisma/schema.prisma (model Campaign).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property int|null $goalAmount
 * @property int $raisedAmount
 * @property string $currency
 * @property Carbon|null $startsAt
 * @property Carbon|null $endsAt
 * @property string|null $projectId
 * @property string|null $coverImageId
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Campaign extends Model
{
    use HasCuid;

    protected $table = 'campaigns';

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
            'status' => ContentStatus::class,
            'goalAmount' => 'integer',
            'raisedAmount' => 'integer',
            'startsAt' => 'datetime',
            'endsAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectId', 'id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'coverImageId', 'id');
    }

    /** @return HasMany<CampaignTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CampaignTranslation::class, 'campaignId', 'id');
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'campaignId', 'id');
    }
}
