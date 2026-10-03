<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Table `event_translations` — generated from prisma/schema.prisma (model EventTranslation).
 *
 * @property string $id
 * @property string $eventId
 * @property Locale $locale
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property mixed $body
 * @property string|null $locationName
 * @property string|null $seoTitle
 * @property string|null $seoDescription
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class EventTranslation extends Model
{
    use HasCuid;

    protected $table = 'event_translations';

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
            'body' => 'array',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'eventId', 'id');
    }
}
