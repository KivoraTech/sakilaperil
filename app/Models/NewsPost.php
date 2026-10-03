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
 * Table `news_posts` — generated from prisma/schema.prisma (model NewsPost).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property Carbon|null $publishedAt
 * @property string|null $coverImageId
 * @property string|null $categoryId
 * @property string|null $projectId
 * @property string|null $authorId
 * @property bool $isFeatured
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class NewsPost extends Model
{
    use HasCuid;

    protected $table = 'news_posts';

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
            'publishedAt' => 'datetime',
            'isFeatured' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'coverImageId', 'id');
    }

    /** @return BelongsTo<NewsCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NewsCategory::class, 'categoryId', 'id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projectId', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorId', 'id');
    }

    /** @return HasMany<NewsPostTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(NewsPostTranslation::class, 'postId', 'id');
    }

    /** @return HasMany<NewsPostTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(NewsPostTag::class, 'postId', 'id');
    }
}
