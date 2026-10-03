<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `campaign_translations` — generated from prisma/schema.prisma (model CampaignTranslation).
 *
 * @property string $id
 * @property string $campaignId
 * @property Locale $locale
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property mixed $body
 */
class CampaignTranslation extends Model
{
    use HasCuid;

    protected $table = 'campaign_translations';

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
            'locale' => Locale::class,
            'body' => 'array',
        ];
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaignId', 'id');
    }
}
