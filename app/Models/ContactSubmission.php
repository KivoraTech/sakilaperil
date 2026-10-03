<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactStatus;
use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `contact_submissions` — generated from prisma/schema.prisma (model ContactSubmission).
 *
 * @property string $id
 * @property ContactStatus $status
 * @property Locale $locale
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $subject
 * @property string $message
 * @property Carbon $consentAt
 * @property string|null $ipHash
 * @property float|null $spamScore
 * @property string|null $internalNote
 * @property string|null $handledById
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class ContactSubmission extends Model
{
    use HasCuid;

    protected $table = 'contact_submissions';

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
            'status' => ContactStatus::class,
            'locale' => Locale::class,
            'consentAt' => 'datetime',
            'spamScore' => 'float',
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
