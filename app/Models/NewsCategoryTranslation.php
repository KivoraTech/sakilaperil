<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `news_category_translations` — generated from prisma/schema.prisma (model NewsCategoryTranslation).
 *
 * @property string $id
 * @property string $categoryId
 * @property Locale $locale
 * @property string $name
 * @property string $slug
 */
class NewsCategoryTranslation extends Model
{
    use HasCuid;

    protected $table = 'news_category_translations';

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

    /** @return BelongsTo<NewsCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'categoryId', 'id');
    }
}
