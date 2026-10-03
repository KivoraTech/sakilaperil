<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `audit_logs` — generated from prisma/schema.prisma (model AuditLog).
 *
 * @property string $id
 * @property string|null $userId
 * @property string $action
 * @property string|null $entityType
 * @property string|null $entityId
 * @property mixed $metadata
 * @property string|null $ipAddress
 * @property string|null $userAgent
 * @property Carbon $createdAt
 */
class AuditLog extends Model
{
    use HasCuid;

    protected $table = 'audit_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'createdAt';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** DATETIME(3) columns, stored in UTC like the reference app. */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'createdAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }
}
