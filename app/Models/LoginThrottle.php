<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `login_throttles` — generated from prisma/schema.prisma (model LoginThrottle).
 *
 * @property string $key
 * @property int $count
 * @property Carbon $windowStart
 * @property Carbon|null $lockedUntil
 * @property Carbon $updatedAt
 */
class LoginThrottle extends Model
{
    protected $table = 'login_throttles';

    protected $primaryKey = 'key';

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
            'count' => 'integer',
            'windowStart' => 'datetime',
            'lockedUntil' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
