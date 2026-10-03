<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `page_preview_drafts` — generated from prisma/schema.prisma (model PagePreviewDraft).
 *
 * @property string $id
 * @property string $userId
 * @property string $pageId
 * @property Locale $locale
 * @property string $clientId
 * @property string $title
 * @property mixed $sections
 * @property Carbon $updatedAt
 */
class PagePreviewDraft extends Model
{
    use HasCuid;

    protected $table = 'page_preview_drafts';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = null;

    public const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'locale' => Locale::class,
            'sections' => 'array',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'pageId', 'id');
    }
}
