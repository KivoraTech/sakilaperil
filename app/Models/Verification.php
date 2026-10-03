<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `verifications` — generated from prisma/schema.prisma (model Verification).
 *
 * @property string $id
 * @property string $identifier
 * @property string $value
 * @property Carbon $expiresAt
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Verification extends Model
{
    use HasCuid;

    protected $table = 'verifications';

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
            'expiresAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
