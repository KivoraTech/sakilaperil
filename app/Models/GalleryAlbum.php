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
 * Table `gallery_albums` — generated from prisma/schema.prisma (model GalleryAlbum).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property string|null $coverImageId
 * @property string|null $projectId
 * @property Carbon|null $takenAt
 * @property int $position
 * @property Carbon|null $publishedAt
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class GalleryAlbum extends Model
{
    use HasCuid;

    protected $table = 'gallery_albums';

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
            'takenAt' => 'datetime',
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

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectId', 'id');
    }

    /** @return HasMany<GalleryAlbumTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(GalleryAlbumTranslation::class, 'albumId', 'id');
    }

    /** @return HasMany<GalleryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class, 'albumId', 'id');
    }
}
