<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `form_rate_limits` — generated from prisma/schema.prisma (model FormRateLimit).
 *
 * @property string $key
 * @property int $count
 * @property Carbon $windowStart
 */
class FormRateLimit extends Model
{
    protected $table = 'form_rate_limits';

    protected $primaryKey = 'key';

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
            'windowStart' => 'datetime',
        ];
    }
}
