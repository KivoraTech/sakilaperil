<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `sessions` — generated from prisma/schema.prisma (model Session).
 *
 * @property string $id
 * @property Carbon $expiresAt
 * @property string $token
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property string $userId
 */
class Session extends Model
{
    use HasCuid;

    protected $table = 'sessions';

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}
