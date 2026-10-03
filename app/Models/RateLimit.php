<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Table `rate_limits` — generated from prisma/schema.prisma (model RateLimit).
 *
 * @property string $id
 * @property string $key
 * @property int $count
 * @property int $lastRequest
 */
class RateLimit extends Model
{
    protected $table = 'rate_limits';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'lastRequest' => 'integer',
        ];
    }
}
