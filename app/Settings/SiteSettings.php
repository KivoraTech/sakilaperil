<?php

declare(strict_types=1);

namespace App\Settings;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Models\SiteSettings as SiteSettingsRow;
use App\Validation\JsonSchemas;
use Illuminate\Support\Facades\Log;

/**
 * Port of sakila/lib/queries/public/settings.ts (getSiteSettings). Each JSON column is validated
 * with the exported Zod schema (resources/schemas/settings.json); an invalid column falls back to
 * DEFAULTS and is logged. Cached under the `settings` tag. Never throws.
 *
 * Shape = SiteSettingsData (sakila/lib/settings/schema.ts) as associative arrays.
 */
final class SiteSettings
{
    /** column => exported schema name */
    public const COLUMNS = [
        'organization' => 'organizationSchema',
        'contact' => 'contactSchema',
        'socialLinks' => 'socialLinksSchema',
        'bankDetails' => 'bankDetailsSchema',
        'nepalPayments' => 'nepalPaymentsSchema',
        'donation' => 'donationSettingsSchema',
        'impactStats' => 'impactStatsSchema',
        'seo' => 'seoSettingsSchema',
        'appearance' => 'appearanceSchema',
        'analytics' => 'analyticsSchema',
        'formMessages' => 'formMessagesSchema',
        'features' => 'featuresSchema',
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'orgName' => 'Sakila per il Nepal',
            'organization' => [
                'legalName' => '[[TODO: legal name]]',
                'taxCode' => '[[TODO: codice fiscale]]',
                'registeredOffice' => '[[TODO: registered office]]',
            ],
            'contact' => ['email' => '[[TODO: contact email]]'],
            'socialLinks' => [],
            'bankDetails' => ['accountHolder' => '[[TODO: account holder]]', 'iban' => '[[TODO: IBAN]]'],
            'nepalPayments' => [],
            'donation' => ['currency' => 'EUR', 'presetAmounts' => [10, 25, 50, 100], 'defaultAmount' => 25, 'impactNotes' => []],
            'impactStats' => [],
            'seo' => [
                'titleTemplate' => ['it' => '%s · Sakila per il Nepal', 'en' => '%s · Sakila per il Nepal'],
                'defaultTitle' => ['it' => 'Sakila per il Nepal', 'en' => 'Sakila per il Nepal'],
                'defaultDescription' => ['it' => 'Sakila per il Nepal', 'en' => 'Sakila per il Nepal'],
            ],
            'appearance' => ['headerStyle' => 'transparent-on-hero', 'enableMotion' => true, 'accent' => 'sindoor'],
            'analytics' => [],
            'formMessages' => [],
            'features' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        return TagCache::remember([CacheTags::SETTINGS], 'settings', fn (): array => $this->load());
    }

    /**
     * Fresh (uncached) settings for the editor: invalid columns are reported in `invalid`.
     *
     * @return array{settings: array<string, mixed>, invalid: list<string>}
     */
    public function forEditor(): array
    {
        $invalid = [];
        $settings = $this->load($invalid);

        return ['settings' => $settings, 'invalid' => $invalid];
    }

    /**
     * @param  list<string>  $invalid
     * @return array<string, mixed>
     */
    private function load(array &$invalid = []): array
    {
        $row = SiteSettingsRow::query()->find('default');
        $defaults = self::defaults();
        if ($row === null) {
            return $defaults;
        }
        $out = ['orgName' => (string) $row->orgName];
        foreach (self::COLUMNS as $column => $schema) {
            $result = JsonSchemas::validate('settings', $schema, $row->getAttribute($column));
            if ($result->ok) {
                $out[$column] = $result->data() ?? [];
            } else {
                Log::error("[settings] invalid {$column}, using defaults", ['issues' => array_slice($result->issues, 0, 5)]);
                $invalid[] = $column;
                $out[$column] = $defaults[$column];
            }
        }

        return $out;
    }
}
