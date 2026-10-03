<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `news_categories` — generated from prisma/schema.prisma (model NewsCategory).
 *
 * @property string $id
 * @property string $key
 * @property int $position
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class NewsCategory extends Model
{
    use HasCuid;

    protected $table = 'news_categories';

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
            'position' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return HasMany<NewsCategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(NewsCategoryTranslation::class, 'categoryId', 'id');
    }

    /** @return HasMany<NewsPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(NewsPost::class, 'categoryId', 'id');
    }
}
