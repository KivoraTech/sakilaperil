<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NavigationLinkType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `navigation_items` — generated from prisma/schema.prisma (model NavigationItem).
 *
 * @property string $id
 * @property string $navigationId
 * @property string|null $parentId
 * @property mixed $label
 * @property NavigationLinkType $linkType
 * @property string|null $pageId
 * @property string|null $routeKey
 * @property string|null $href
 * @property bool $openInNewTab
 * @property bool $isVisible
 * @property int $position
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class NavigationItem extends Model
{
    use HasCuid;

    protected $table = 'navigation_items';

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
            'label' => 'array',
            'linkType' => NavigationLinkType::class,
            'openInNewTab' => 'boolean',
            'isVisible' => 'boolean',
            'position' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Navigation, $this> */
    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class, 'navigationId', 'id');
    }

    /** @return BelongsTo<NavigationItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'parentId', 'id');
    }

    /** @return HasMany<NavigationItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'parentId', 'id');
    }

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'pageId', 'id');
    }
}
