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
 * Table `events` — generated from prisma/schema.prisma (model Event).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property Carbon $startsAt
 * @property Carbon|null $endsAt
 * @property bool $allDay
 * @property string $timezone
 * @property string|null $address
 * @property string|null $mapUrl
 * @property string|null $onlineUrl
 * @property string|null $registrationUrl
 * @property string|null $coverImageId
 * @property bool $isFeatured
 * @property Carbon|null $publishedAt
 * @property string|null $createdById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Event extends Model
{
    use HasCuid;

    protected $table = 'events';

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
            'startsAt' => 'datetime',
            'endsAt' => 'datetime',
            'allDay' => 'boolean',
            'isFeatured' => 'boolean',
            'publishedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'coverImageId', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById', 'id');
    }

    /** @return HasMany<EventTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(EventTranslation::class, 'eventId', 'id');
    }
}
