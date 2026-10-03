<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `news_tags` — generated from prisma/schema.prisma (model NewsTag).
 *
 * @property string $id
 * @property Carbon $createdAt
 */
class NewsTag extends Model
{
    use HasCuid;

    protected $table = 'news_tags';

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
            'createdAt' => 'datetime',
        ];
    }

    /** @return HasMany<NewsTagTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(NewsTagTranslation::class, 'tagId', 'id');
    }

    /** @return HasMany<NewsPostTag, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(NewsPostTag::class, 'tagId', 'id');
    }
}
