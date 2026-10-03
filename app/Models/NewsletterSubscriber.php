<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\NewsletterStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `newsletter_subscribers` — generated from prisma/schema.prisma (model NewsletterSubscriber).
 *
 * @property string $id
 * @property string $email
 * @property Locale $locale
 * @property NewsletterStatus $status
 * @property string|null $provider
 * @property string|null $providerId
 * @property string|null $source
 * @property Carbon $consentAt
 * @property Carbon|null $confirmedAt
 * @property Carbon|null $unsubscribedAt
 * @property string|null $syncError
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class NewsletterSubscriber extends Model
{
    use HasCuid;

    protected $table = 'newsletter_subscribers';

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
            'locale' => Locale::class,
            'status' => NewsletterStatus::class,
            'consentAt' => 'datetime',
            'confirmedAt' => 'datetime',
            'unsubscribedAt' => 'datetime',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }
}
