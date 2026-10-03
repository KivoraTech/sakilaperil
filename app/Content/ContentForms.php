<?php

declare(strict_types=1);

namespace App\Content;

use App\I18n\Routes;
use App\RichText\Video;

/**
 * Server-side parsers for the content forms — port of resources/js/lib/content/schemas.ts (the
 * same Zod schemas validate the React forms in the browser). Each parse*() returns the normalised
 * values or throws a ValidationException with field paths identical to the form's.
 */
final class ContentForms
{
    public const PROJECT_STAGES = ['PLANNED', 'ACTIVE', 'COMPLETED'];

    public const CURRENCIES = ['EUR', 'NPR', 'USD'];

    public const EVENT_TIMEZONES = ['Europe/Rome', 'Asia/Kathmandu'];

    public const REPORT_TYPES = ['ANNUAL_REPORT', 'FINANCIAL_STATEMENT', 'SOCIAL_REPORT', 'OTHER'];

    public const REPORT_TYPE_LABELS = [
        'ANNUAL_REPORT' => 'Annual report',
        'FINANCIAL_STATEMENT' => 'Financial statement',
        'SOCIAL_REPORT' => 'Social report',
        'OTHER' => 'Other',
    ];

    public const SAVE_INTENTS = ['save', 'publish'];

    /** @return 'save'|'publish' */
    public static function intent(mixed $v): string
    {
        $in = Input::from(['intent' => $v ?? 'save']);
        $intent = $in->enum('intent', self::SAVE_INTENTS);
        $in->validate();

        return $intent === 'publish' ? 'publish' : 'save';
    }

    public static function status(mixed $v): string
    {
        $in = Input::from(['status' => $v]);
        $s = $in->enum('status', ContentCommon::STATUSES);
        $in->validate();

        return $s;
    }

    public static function id(mixed $v): string
    {
        $in = Input::from(['id' => $v]);
        $id = $in->id('id');
        $in->validate();

        return $id;
    }

    // ── Translations ─────────────────────────────────────────────────

    /**
     * `translations({...shape})`: Italian title+slug required; English optional (`enabled`).
     *
     * @param  callable(Input, string): array<string, mixed>  $shape  reads the extra fields under the given prefix
     * @return array{it: array<string, mixed>, en: array<string, mixed>}
     */
    private static function translations(Input $in, callable $shape): array
    {
        $out = [];
        foreach (['it', 'en'] as $locale) {
            $p = "translations.{$locale}";
            $t = [
                'title' => $in->str("{$p}.title", 200),
                'slug' => $in->str("{$p}.slug", 80),
                ...$shape($in, $p),
            ];
            $required = true;
            if ($locale === 'en') {
                $t['enabled'] = $in->bool("{$p}.enabled");
                $required = $t['enabled'];
            }
            if ($required) {
                self::requireTitleSlug($in, $p, $t['title'], $t['slug']);
            }
            $out[$locale] = $t;
        }

        /** @var array{it: array<string, mixed>, en: array<string, mixed>} $out */
        return $out;
    }

    private static function requireTitleSlug(Input $in, string $p, string $title, string $slug): void
    {
        if ($title === '') {
            $in->error("{$p}.title", 'Title is required');
        }
        if ($slug === '') {
            $in->error("{$p}.slug", 'Slug is required');
        } elseif (preg_match(Routes::SLUG_PATTERN, $slug) !== 1) {
            $in->error("{$p}.slug", 'Lowercase letters, numbers and dashes only');
        }
    }

    /** @return array{seoTitle: string, seoDescription: string} */
    private static function seo(Input $in, string $p): array
    {
        return ['seoTitle' => $in->str("{$p}.seoTitle", 70), 'seoDescription' => $in->str("{$p}.seoDescription", 170)];
    }

    // ── News ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function news(mixed $data): array
    {
        $in = Input::from($data);
        $tags = [];
        foreach ($in->list('tags', 10) as $i => $_) {
            $tag = $in->str("tags.{$i}", 40);
            if ($tag === '' && ! $in->hasError("tags.{$i}")) {
                $in->error("tags.{$i}", 'Too small: expected string to have >=1 characters');
            }
            $tags[] = $tag;
        }
        $v = [
            'publishedAt' => $in->isoOrEmpty('publishedAt'),
            'coverImageId' => $in->optionalId('coverImageId'),
            'categoryId' => $in->optionalId('categoryId'),
            'projectId' => $in->optionalId('projectId'),
            'authorId' => $in->optionalId('authorId'),
            'isFeatured' => $in->bool('isFeatured'),
            'tags' => $tags,
            'translations' => self::translations($in, fn (Input $in, string $p): array => [
                'excerpt' => $in->str("{$p}.excerpt", 300),
                'body' => $in->doc("{$p}.body"),
                ...self::seo($in, $p),
            ]),
        ];
        $in->validate();

        return $v;
    }

    // ── Projects ─────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function project(mixed $data): array
    {
        $in = Input::from($data);
        $v = [
            'stage' => $in->enum('stage', self::PROJECT_STAGES),
            'district' => $in->str('district', 100),
            'latitude' => self::coord($in, 'latitude', 90),
            'longitude' => self::coord($in, 'longitude', 180),
            'startDate' => $in->isoOrEmpty('startDate'),
            'endDate' => $in->isoOrEmpty('endDate'),
            'goalAmount' => self::money($in, 'goalAmount'),
            'raisedAmount' => self::money($in, 'raisedAmount'),
            'currency' => $in->enum('currency', self::CURRENCIES),
            'coverImageId' => $in->optionalId('coverImageId'),
            'isFeatured' => $in->bool('isFeatured'),
            'position' => $in->int('position', 0, 100000),
            'publishedAt' => $in->isoOrEmpty('publishedAt'),
            'translations' => self::translations($in, fn (Input $in, string $p): array => [
                'sector' => $in->str("{$p}.sector", 60),
                'metaLine' => $in->str("{$p}.metaLine", 120),
                'summary' => $in->str("{$p}.summary", 400),
                'body' => $in->doc("{$p}.body"),
                ...self::seo($in, $p),
            ]),
        ];
        $in->validate();

        return $v;
    }

    private static function money(Input $in, string $path): string
    {
        $v = $in->str($path, 15);
        if ($v !== '' && preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $v) !== 1) {
            $in->error($path, 'Enter an amount like 1500 or 1500.50');
        }

        return $v;
    }

    private static function coord(Input $in, string $path, int $max): string
    {
        $v = $in->str($path, 20);
        if ($v !== '' && (! is_numeric($v) || ! is_finite((float) $v) || abs((float) $v) > $max)) {
            $in->error($path, "Between -{$max} and {$max}");
        }

        return $v;
    }

    /** Major-unit string → integer minor units (null when empty). */
    public static function toMinorUnits(string $v): ?int
    {
        if ($v === '') {
            return null;
        }
        [$int, $dec] = array_pad(explode('.', $v, 2), 2, '');

        return (int) $int * 100 + (int) substr($dec.'00', 0, 2);
    }

    public static function fromMinorUnits(?int $v): string
    {
        if ($v === null) {
            return '';
        }

        return $v % 100 === 0 ? (string) intdiv($v, 100) : number_format($v / 100, 2, '.', '');
    }

    // ── Events ───────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function event(mixed $data): array
    {
        $in = Input::from($data);
        $v = [
            'startsAt' => $in->iso('startsAt', 'Start date is required'),
            'endsAt' => $in->isoOrEmpty('endsAt'),
            'allDay' => $in->bool('allDay'),
            'timezone' => $in->enum('timezone', self::EVENT_TIMEZONES),
            'address' => $in->str('address', 200),
            'mapUrl' => $in->httpsUrlOrEmpty('mapUrl'),
            'onlineUrl' => $in->httpsUrlOrEmpty('onlineUrl'),
            'registrationUrl' => $in->httpsUrlOrEmpty('registrationUrl'),
            'coverImageId' => $in->optionalId('coverImageId'),
            'isFeatured' => $in->bool('isFeatured'),
            'publishedAt' => $in->isoOrEmpty('publishedAt'),
            'translations' => self::translations($in, fn (Input $in, string $p): array => [
                'summary' => $in->str("{$p}.summary", 400),
                'locationName' => $in->str("{$p}.locationName", 120),
                'body' => $in->doc("{$p}.body"),
                ...self::seo($in, $p),
            ]),
        ];
        if ($v['endsAt'] !== '' && $v['startsAt'] !== '' && strtotime($v['endsAt']) < strtotime($v['startsAt'])) {
            $in->error('endsAt', 'The end must be after the start');
        }
        $in->validate();

        return $v;
    }

    // ── Gallery albums ───────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function album(mixed $data): array
    {
        $in = Input::from($data);
        $items = [];
        foreach ($in->list('items', 300) as $n => $_) {
            $p = "items.{$n}";
            $item = [
                'key' => $in->str("{$p}.key", 64),
                'type' => $in->enum("{$p}.type", ['PHOTO', 'VIDEO']),
                'mediaAssetId' => $in->optionalId("{$p}.mediaAssetId"),
                'videoProvider' => $in->enumOrNull("{$p}.videoProvider", ['youtube', 'vimeo']),
                'videoId' => $in->strOrNull("{$p}.videoId", 20),
                'captionIt' => $in->str("{$p}.captionIt", 300),
                'captionEn' => $in->str("{$p}.captionEn", 300),
            ];
            if ($item['type'] === 'PHOTO' && $item['mediaAssetId'] === null) {
                $in->error("{$p}.mediaAssetId", 'Choose a photo');
            }
            if ($item['type'] === 'VIDEO' && ! Video::isValidId($item['videoProvider'], $item['videoId'])) {
                $in->error("{$p}.videoId", 'Invalid YouTube/Vimeo video');
            }
            $items[] = $item;
        }
        $v = [
            'coverImageId' => $in->optionalId('coverImageId'),
            'projectId' => $in->optionalId('projectId'),
            'takenAt' => $in->isoOrEmpty('takenAt'),
            'position' => $in->int('position', 0, 100000),
            'publishedAt' => $in->isoOrEmpty('publishedAt'),
            'items' => $items,
            'translations' => self::translations($in, fn (Input $in, string $p): array => [
                'description' => $in->str("{$p}.description", 1000),
            ]),
        ];
        $in->validate();

        return $v;
    }

    // ── Reports ──────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public static function report(mixed $data): array
    {
        $in = Input::from($data);
        $tr = [];
        foreach (['it', 'en'] as $locale) {
            $p = "translations.{$locale}";
            $t = ['title' => $in->str("{$p}.title", 200), 'description' => $in->str("{$p}.description", 1000)];
            $required = true;
            if ($locale === 'en') {
                $t['enabled'] = $in->bool("{$p}.enabled");
                $t['fileId'] = $in->optionalId("{$p}.fileId");
                $required = $t['enabled'];
            }
            if ($required && $t['title'] === '') {
                $in->error("{$p}.title", 'Title is required');
            }
            $tr[$locale] = $t;
        }
        $v = [
            'type' => $in->enum('type', self::REPORT_TYPES),
            'year' => $in->int('year', 1990, 2100),
            'fileId' => $in->optionalId('fileId'),
            'coverImageId' => $in->optionalId('coverImageId'),
            'position' => $in->int('position', 0, 100000),
            'publishedAt' => $in->isoOrEmpty('publishedAt'),
            'translations' => $tr,
        ];
        $in->validate();

        return $v;
    }

    // ── Media metadata ───────────────────────────────────────────────

    /** @return array{altIt: string, altEn: string, captionIt: string, captionEn: string, credit: string, focalX: ?float, focalY: ?float} */
    public static function mediaMeta(mixed $data): array
    {
        $in = Input::from($data);
        $focal = function (string $path) use ($in): ?float {
            $v = $in->raw($path);
            if ($v === null) {
                return null;
            }
            if (! is_int($v) && ! is_float($v)) {
                $in->error($path, 'Invalid input: expected number');

                return null;
            }
            if ($v < 0 || $v > 1) {
                $in->error($path, 'Between 0 and 1');
            }

            return (float) $v;
        };
        $v = [
            'altIt' => $in->str('altIt', 300),
            'altEn' => $in->str('altEn', 300),
            'captionIt' => $in->str('captionIt', 300),
            'captionEn' => $in->str('captionEn', 300),
            'credit' => $in->str('credit', 200),
            'focalX' => $focal('focalX'),
            'focalY' => $focal('focalY'),
        ];
        $in->validate();

        return $v;
    }
}
