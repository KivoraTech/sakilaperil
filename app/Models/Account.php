<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `accounts` — generated from prisma/schema.prisma (model Account).
 *
 * @property string $id
 * @property string $accountId
 * @property string $providerId
 * @property string $userId
 * @property string|null $accessToken
 * @property string|null $refreshToken
 * @property string|null $idToken
 * @property Carbon|null $accessTokenExpiresAt
 * @property Carbon|null $refreshTokenExpiresAt
 * @property string|null $scope
 * @property string|null $password
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Account extends Model
{
    use HasCuid;

    protected $table = 'accounts';

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
            'accessTokenExpiresAt' => 'datetime',
            'refreshTokenExpiresAt' => 'datetime',
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
