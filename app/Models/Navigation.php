<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `navigations` — generated from prisma/schema.prisma (model Navigation).
 *
 * @property string $id
 * @property string $key
 * @property string $name
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Navigation extends Model
{
    use HasCuid;

    protected $table = 'navigations';

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
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return HasMany<NavigationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'navigationId', 'id');
    }
}
