<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\ReportType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Table `reports` — generated from prisma/schema.prisma (model Report).
 *
 * @property string $id
 * @property ContentStatus $status
 * @property ReportType $type
 * @property int $year
 * @property string|null $fileId
 * @property string|null $coverImageId
 * @property Carbon|null $publishedAt
 * @property int $position
 * @property Carbon $createdAt
 * @property Carbon $updatedAt
 */
class Report extends Model
{
    use HasCuid;

    protected $table = 'reports';

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
            'status' => ContentStatus::class,
            'type' => ReportType::class,
            'year' => 'integer',
            'publishedAt' => 'datetime',
            'position' => 'integer',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'fileId', 'id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function coverImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'coverImageId', 'id');
    }

    /** @return HasMany<ReportTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ReportTranslation::class, 'reportId', 'id');
    }
}
