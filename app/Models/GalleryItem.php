<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GalleryItemType;
use App\Enums\VideoProvider;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `gallery_items` — generated from prisma/schema.prisma (model GalleryItem).
 *
 * @property string $id
 * @property string $albumId
 * @property GalleryItemType $type
 * @property string|null $mediaAssetId
 * @property VideoProvider|null $videoProvider
 * @property string|null $videoId
 * @property mixed $caption
 * @property int $position
 * @property Carbon $createdAt
 */
class GalleryItem extends Model
{
    use HasCuid;

    protected $table = 'gallery_items';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => GalleryItemType::class,
            'videoProvider' => VideoProvider::class,
            'caption' => 'array',
            'position' => 'integer',
            'createdAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<GalleryAlbum, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'albumId', 'id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'mediaAssetId', 'id');
    }
}
