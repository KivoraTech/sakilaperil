<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `news_post_translations` — generated from prisma/schema.prisma (model NewsPostTranslation).
 *
 * @property string $id
 * @property string $postId
 * @property Locale $locale
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property mixed $body
 * @property string|null $seoTitle
 * @property string|null $seoDescription
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class NewsPostTranslation extends Model
{
    use HasCuid;

    protected $table = 'news_post_translations';

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
            'locale' => Locale::class,
            'body' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<NewsPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class, 'postId', 'id');
    }
}
