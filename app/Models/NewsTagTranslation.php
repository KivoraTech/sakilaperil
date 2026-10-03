<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `news_tag_translations` — generated from prisma/schema.prisma (model NewsTagTranslation).
 *
 * @property string $id
 * @property string $tagId
 * @property Locale $locale
 * @property string $name
 * @property string $slug
 */
class NewsTagTranslation extends Model
{
    use HasCuid;

    protected $table = 'news_tag_translations';

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

    /** @return BelongsTo<NewsTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(NewsTag::class, 'tagId', 'id');
    }
}
