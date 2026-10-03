<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaKind;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `media_assets` — generated from prisma/schema.prisma (model MediaAsset).
 *
 * @property string $id
 * @property MediaKind $kind
 * @property string $storageDriver
 * @property string $storageKey
 * @property string $url
 * @property string $filename
 * @property string $mimeType
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property mixed $alt
 * @property mixed $caption
 * @property string|null $credit
 * @property float|null $focalX
 * @property float|null $focalY
 * @property string|null $uploadedById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class MediaAsset extends Model
{
    use HasCuid;

    protected $table = 'media_assets';

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
            'kind' => MediaKind::class,
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'alt' => 'array',
            'caption' => 'array',
            'focalX' => 'float',
            'focalY' => 'float',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploadedById', 'id');
    }

    /** @return HasMany<Project, $this> */
    public function projectCovers(): HasMany
    {
        return $this->hasMany(Project::class, 'coverImageId', 'id');
    }

    /** @return HasMany<NewsPost, $this> */
    public function newsCovers(): HasMany
    {
        return $this->hasMany(NewsPost::class, 'coverImageId', 'id');
    }

    /** @return HasMany<Event, $this> */
    public function eventCovers(): HasMany
    {
        return $this->hasMany(Event::class, 'coverImageId', 'id');
    }

    /** @return HasMany<GalleryAlbum, $this> */
    public function albumCovers(): HasMany
    {
        return $this->hasMany(GalleryAlbum::class, 'coverImageId', 'id');
    }

    /** @return HasMany<GalleryItem, $this> */
    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class, 'mediaAssetId', 'id');
    }

    /** @return HasMany<Report, $this> */
    public function reportFiles(): HasMany
    {
        return $this->hasMany(Report::class, 'fileId', 'id');
    }

    /** @return HasMany<Report, $this> */
    public function reportCovers(): HasMany
    {
        return $this->hasMany(Report::class, 'coverImageId', 'id');
    }

    /** @return HasMany<ReportTranslation, $this> */
    public function reportTrFiles(): HasMany
    {
        return $this->hasMany(ReportTranslation::class, 'fileId', 'id');
    }

    /** @return HasMany<Campaign, $this> */
    public function campaignCovers(): HasMany
    {
        return $this->hasMany(Campaign::class, 'coverImageId', 'id');
    }

    /** @return HasMany<PageTranslation, $this> */
    public function pageOgImages(): HasMany
    {
        return $this->hasMany(PageTranslation::class, 'ogImageId', 'id');
    }
}
