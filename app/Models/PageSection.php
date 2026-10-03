<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `page_sections` — generated from prisma/schema.prisma (model PageSection).
 *
 * @property string $id
 * @property string $pageId
 * @property Locale $locale
 * @property string $type
 * @property int $schemaVersion
 * @property int $position
 * @property bool $isVisible
 * @property mixed $content
 * @property mixed $settings
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class PageSection extends Model
{
    use HasCuid;

    protected $table = 'page_sections';

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
            'schemaVersion' => 'integer',
            'position' => 'integer',
            'isVisible' => 'boolean',
            'content' => 'array',
            'settings' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'pageId', 'id');
    }
}
