<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `gallery_album_translations` — generated from prisma/schema.prisma (model GalleryAlbumTranslation).
 *
 * @property string $id
 * @property string $albumId
 * @property Locale $locale
 * @property string $title
 * @property string $slug
 * @property string|null $description
 */
class GalleryAlbumTranslation extends Model
{
    use HasCuid;

    protected $table = 'gallery_album_translations';

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
            'locale' => Locale::class,
        ];
    }

    /** @return BelongsTo<GalleryAlbum, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class, 'albumId', 'id');
    }
}
