<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `page_translations` — generated from prisma/schema.prisma (model PageTranslation).
 *
 * @property string $id
 * @property string $pageId
 * @property Locale $locale
 * @property string $title
 * @property string $slug
 * @property string|null $seoTitle
 * @property string|null $seoDescription
 * @property string|null $ogImageId
 * @property bool $noIndex
 * @property string|null $publishedRevisionId
 * @property Carbon|null $publishedAt
 * @property bool $hasDraftChanges
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class PageTranslation extends Model
{
    use HasCuid;

    protected $table = 'page_translations';

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
            'noIndex' => 'boolean',
            'publishedAt' => 'datetime',
            'hasDraftChanges' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'pageId', 'id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'ogImageId', 'id');
    }

    /** @return BelongsTo<PageRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(PageRevision::class, 'publishedRevisionId', 'id');
    }
}
