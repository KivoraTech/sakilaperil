<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Table `page_revisions` — generated from prisma/schema.prisma (model PageRevision).
 *
 * @property string $id
 * @property string $pageId
 * @property Locale $locale
 * @property mixed $snapshot
 * @property string|null $createdById
 * @property Carbon $createdAt
 */
class PageRevision extends Model
{
    use HasCuid;

    protected $table = 'page_revisions';

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
            'locale' => Locale::class,
            'snapshot' => 'array',
            'createdAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'pageId', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createdById', 'id');
    }

    /** @return HasOne<PageTranslation, $this> */
    public function publishedFor(): HasOne
    {
        return $this->hasOne(PageTranslation::class, 'publishedRevisionId', 'id');
    }
}
