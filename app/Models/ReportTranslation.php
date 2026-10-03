<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Table `report_translations` — generated from prisma/schema.prisma (model ReportTranslation).
 *
 * @property string $id
 * @property string $reportId
 * @property Locale $locale
 * @property string $title
 * @property string|null $description
 * @property string|null $fileId
 */
class ReportTranslation extends Model
{
    use HasCuid;

    protected $table = 'report_translations';

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
        ];
    }

    /** @return BelongsTo<Report, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'reportId', 'id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'fileId', 'id');
    }
}
