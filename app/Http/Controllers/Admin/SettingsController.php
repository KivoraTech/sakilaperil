<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\SiteSettings as SiteSettingsRow;
use App\Models\User;
use App\Pages\Input;
use App\Pages\Json;
use App\Pages\PageCache;
use App\Queries\Admin\PagesQuery;
use App\Settings\SettingsRules;
use App\Settings\SiteSettings;
use App\Validation\JsonSchemas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/** Site settings editor + save (reference app/admin/(shell)/settings + lib/actions/settings.ts). */
final class SettingsController extends Controller
{
    public const SECTIONS = ['organization', 'contact', 'donations', 'impact', 'seo', 'appearance', 'messages'];

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        PagesQuery::authorize($user, 'settings.manage');

        return Inertia::render('admin/settings/index', [
            'tab' => is_string($request->query('tab')) ? (string) $request->query('tab') : 'organization',
            ...self::editorData(),
            'refs' => PagesQuery::references($user, 'it'),
        ]);
    }

    /**
     * Fresh (uncached) settings for the editor. Each JSON column is validated separately (objects
     * kept as `{}`); an invalid column falls back to defaults and is reported.
     *
     * @return array{settings: stdClass, invalid: list<string>, updatedAt: ?string, updatedBy: ?string}
     */
    public static function editorData(): array
    {
        $row = DB::table('site_settings')->where('id', 'default')->first();
        $defaults = SiteSettings::defaults();
        $settings = new stdClass;
        $invalid = [];
        $settings->orgName = $row !== null ? (string) $row->orgName : $defaults['orgName'];
        foreach (SiteSettings::COLUMNS as $column => $schema) {
            $raw = $row !== null && is_string($row->{$column} ?? null) ? json_decode($row->{$column}, false) : null;
            $result = $row !== null ? JsonSchemas::validate('settings', $schema, $raw) : null;
            if ($result !== null && $result->ok) {
                $settings->{$column} = $result->object;
            } else {
                if ($row !== null) {
                    $invalid[] = $column;
                }
                $settings->{$column} = JsonSchemas::validate('settings', $schema, json_decode((string) json_encode($defaults[$column]), false))->object;
            }
        }
        $updatedBy = null;
        if ($row !== null && $row->updatedById !== null) {
            $updatedBy = User::query()->whereKey($row->updatedById)->value('name');
        }

        return [
            'settings' => $settings,
            'invalid' => $invalid,
            'updatedAt' => $row !== null ? Json::iso(Carbon::parse($row->updatedAt, 'UTC')) : null,
            'updatedBy' => is_string($updatedBy) ? $updatedBy : null,
        ];
    }

    /**
     * Save one section of the site settings (only its own columns). Settings are live immediately
     * (header/footer, donate widget, SEO defaults), so the `settings` tag is expired.
     */
    public function update(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            /** @var User $user */
            $user = $request->user();
            $body = Json::object(Input::body($request));
            $section = $body->section ?? null;
            if (! in_array($section, self::SECTIONS, true)) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['section' => ['Invalid option']]);
            }
            $result = JsonSchemas::validate('settings-sections', "settingsSectionSchemas.{$section}", self::stripEmpty($body->data ?? null) ?? new stdClass);
            if (! $result->ok || ! $result->object instanceof stdClass) {
                $fieldErrors = [];
                foreach ($result->issues as $issue) {
                    $fieldErrors[implode('.', $issue['path'])][] = $issue['message'];
                }

                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', $fieldErrors);
            }
            $values = $result->object;

            // Format rules JSON Schema cannot express (email, phone, IBAN checksum, amounts, unique keys).
            $ruleErrors = SettingsRules::check((string) $section, $values);
            if ($ruleErrors !== []) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', $ruleErrors);
            }

            // Referenced images must exist (logo, favicon, QR codes, sharing image).
            $imageIds = self::collectImageIds($values);
            if ($imageIds !== [] && MediaAsset::query()->whereIn('id', $imageIds)->where('kind', 'IMAGE')->count() !== count($imageIds)) {
                return ActionResult::fail('VALIDATION', 'One of the selected images no longer exists.');
            }

            // An installation without the content import has no settings row yet: start from the defaults.
            if (! SiteSettingsRow::query()->whereKey('default')->exists()) {
                self::insertDefaultRow();
            }
            $update = ['updatedById' => $user->id, 'updatedAt' => now('UTC')->format('Y-m-d H:i:s.v')];
            foreach (get_object_vars($values) as $key => $value) {
                if ($key === 'orgName') {
                    $update['orgName'] = (string) $value;
                } elseif (array_key_exists($key, SiteSettings::COLUMNS)) {
                    $update[$key] = JsonSchemas::encode($value);
                }
            }
            DB::table('site_settings')->where('id', 'default')->update($update);

            Audit::log(AuditAction::SETTINGS_UPDATED, $user->id, 'SiteSettings', 'default', [
                // Values are not logged in full (IBAN etc.), only which parts changed.
                'section' => $section, 'fields' => array_keys(get_object_vars($values)),
            ]);
            PageCache::expireSettings();

            return ActionResult::ok(null, 'Settings saved — live on the website');
        });
    }

    /** The single settings row with every column at its (schema-validated) default. */
    private static function insertDefaultRow(): void
    {
        $defaults = SiteSettings::defaults();
        $now = now('UTC')->format('Y-m-d H:i:s.v');
        $insert = ['id' => 'default', 'orgName' => (string) $defaults['orgName'], 'updatedAt' => $now, 'createdAt' => $now];
        foreach (SiteSettings::COLUMNS as $column => $schema) {
            $value = JsonSchemas::validate('settings', $schema, json_decode((string) json_encode($defaults[$column]), false))->object;
            $insert[$column] = JsonSchemas::encode($value);
        }
        DB::table('site_settings')->insertOrIgnore($insert);
    }

    /** Port of stripEmpty() (resources/js/lib/settings/sections.ts): drop blank strings / empty nested objects. */
    public static function stripEmpty(mixed $value, int $depth = 0): mixed
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => self::stripEmpty($v, $depth + 1), $value), fn ($v) => $v !== null));
        }
        if (is_string($value)) {
            return trim($value) === '' ? null : $value;
        }
        if (is_float($value) && is_nan($value)) {
            return null;
        }
        if ($value instanceof stdClass) {
            $out = new stdClass;
            foreach (get_object_vars($value) as $k => $v) {
                $c = self::stripEmpty($v, $depth + 1);
                if ($c !== null) {
                    $out->{$k} = $c;
                }
            }

            return $depth >= 2 && get_object_vars($out) === [] ? null : $out;
        }

        return $value;
    }

    /** @return list<string> */
    private static function collectImageIds(mixed $values): array
    {
        $ids = [];
        $walk = function (mixed $v) use (&$walk, &$ids): void {
            if (is_array($v)) {
                foreach ($v as $x) {
                    $walk($x);
                }
            } elseif ($v instanceof stdClass) {
                foreach (get_object_vars($v) as $k => $x) {
                    if (preg_match('/ImageId$/', (string) $k) === 1 && is_string($x) && $x !== '') {
                        $ids[$x] = true;
                    } else {
                        $walk($x);
                    }
                }
            }
        };
        $walk($values);

        return array_keys($ids);
    }
}
