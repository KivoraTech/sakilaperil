<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ProjectStage;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `projects` — generated from prisma/schema.prisma (model Project).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property ProjectStage $stage
 * @property string|null $district
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $startDate
 * @property Carbon|null $endDate
 * @property int|null $goalAmount
 * @property int|null $raisedAmount
 * @property string $currency
 * @property string|null $coverImageId
 * @property bool $isFeatured
 * @property int $position
 * @property Carbon|null $publishedAt
 * @property string|null $createdById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Project extends Model
{
    use HasCuid;

    protected $table = 'projects';

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
            'stage' => ProjectStage::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'startDate' => 'datetime',
            'endDate' => 'datetime',
            'goalAmount' => 'integer',
            'raisedAmount' => 'integer',
            'isFeatured' => 'boolean',
            'position' => 'integer',
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

    /** @return HasMany<ProjectTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProjectTranslation::class, 'projectId', 'id');
    }

    /** @return HasMany<NewsPost, $this> */
    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class, 'projectId', 'id');
    }

    /** @return HasMany<Campaign, $this> */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'projectId', 'id');
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'projectId', 'id');
    }

    /** @return HasMany<GalleryAlbum, $this> */
    public function albums(): HasMany
    {
        return $this->hasMany(GalleryAlbum::class, 'projectId', 'id');
    }
}
