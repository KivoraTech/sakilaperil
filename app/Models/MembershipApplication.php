<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `membership_applications` — generated from prisma/schema.prisma (model MembershipApplication).
 *
 * @property string $id
 * @property SubmissionStatus $status
 * @property Locale $locale
 * @property string $firstName
 * @property string $lastName
 * @property string $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string $membershipType
 * @property string|null $message
 * @property Carbon $consentAt
 * @property string|null $internalNote
 * @property string|null $handledById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class MembershipApplication extends Model
{
    use HasCuid;

    protected $table = 'membership_applications';

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
            'status' => SubmissionStatus::class,
            'locale' => Locale::class,
            'consentAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handledById', 'id');
    }
}
