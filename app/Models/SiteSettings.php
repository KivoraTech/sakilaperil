<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Table `site_settings` — generated from prisma/schema.prisma (model SiteSettings).
 *
 * @property string $id
 * @property string $orgName
 * @property mixed $organization
 * @property mixed $contact
 * @property mixed $socialLinks
 * @property mixed $bankDetails
 * @property mixed $nepalPayments
 * @property mixed $donation
 * @property mixed $impactStats
 * @property mixed $seo
 * @property mixed $appearance
 * @property mixed $analytics
 * @property mixed $formMessages
 * @property mixed $features
 * @property string|null $updatedById
 * @property Carbon $updatedAt
 * @property Carbon $createdAt
 */
class SiteSettings extends Model
{
    protected $table = 'site_settings';

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
            'organization' => 'array',
            'contact' => 'array',
            'socialLinks' => 'array',
            'bankDetails' => 'array',
            'nepalPayments' => 'array',
            'donation' => 'array',
            'impactStats' => 'array',
            'seo' => 'array',
            'appearance' => 'array',
            'analytics' => 'array',
            'formMessages' => 'array',
            'features' => 'array',
            'updatedAt' => 'datetime',
            'createdAt' => 'datetime',
        ];
    }
}
