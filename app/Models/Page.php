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
 * Table `pages` — generated from prisma/schema.prisma (model Page).
 *
 * @property string $id
 * @property string|null $key
 * @property ContentStatus $status
 * @property string|null $createdById
 * @property string|null $updatedById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Page extends Model
{
    use HasCuid;

    protected $table = 'pages';

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
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updatedById', 'id');
    }

    /** @return HasMany<PageTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class, 'pageId', 'id');
    }

    /** @return HasMany<PageSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class, 'pageId', 'id');
    }

    /** @return HasMany<PageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class, 'pageId', 'id');
    }

    /** @return HasMany<NavigationItem, $this> */
    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'pageId', 'id');
    }

    /** @return HasMany<PagePreviewDraft, $this> */
    public function previewDrafts(): HasMany
    {
        return $this->hasMany(PagePreviewDraft::class, 'pageId', 'id');
    }
}
