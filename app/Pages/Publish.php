<?php

declare(strict_types=1);

namespace App\Pages;

use App\Blocks\BlockRegistry;
use stdClass;
use Throwable;

/**
 * Pure publish/validation logic for the page builder — port of resources/js/lib/pages/publish.ts
 * and snapshot.ts. Stored rows are validated with the JSON Schemas exported from the same Zod
 * block registry the admin uses. Validated data keeps stdClass objects so `{}` stays `{}`.
 *
 * A "row" is ['id', 'type', 'schemaVersion', 'isVisible', 'position', 'content', 'settings'].
 */
final class Publish
{
    private const REASON_TEXT = [
        'UNKNOWN_TYPE' => 'This block type no longer exists',
        'FUTURE_VERSION' => 'This block was saved by a newer version of the CMS',
        'MIGRATION_FAILED' => 'This block could not be upgraded to the current version',
        'INVALID' => 'Some fields are missing or invalid',
    ];

    public static function blockLabel(string $type): string
    {
        return BlockRegistry::isBlockType($type) ? (string) BlockRegistry::definition($type)['label'] : $type;
    }

    /**
     * Migrate + validate one row. On success content/settings are Zod-output shaped (defaults applied).
     *
     * @param  array<string, mixed>  $row
     * @return array{ok: true, content: mixed, settings: mixed}|array{ok: false, reason: string, issues: list<array{path: list<string>, message: string}>}
     */
    public static function parse(array $row): array
    {
        $type = (string) $row['type'];
        if (! BlockRegistry::isBlockType($type)) {
            return ['ok' => false, 'reason' => 'UNKNOWN_TYPE', 'issues' => []];
        }
        $version = (int) ($row['schemaVersion'] ?? 1);
        if ($version > BlockRegistry::version($type)) {
            return ['ok' => false, 'reason' => 'FUTURE_VERSION', 'issues' => []];
        }
        try {
            $raw = BlockRegistry::migrate($type, $version, [
                'content' => $row['content'] ?? null,
                'settings' => $row['settings'] ?? new stdClass,
            ]);
        } catch (Throwable) {
            return ['ok' => false, 'reason' => 'MIGRATION_FAILED', 'issues' => []];
        }
        $result = BlockRegistry::validateInput($type, $raw['content'], $raw['settings'] ?? new stdClass);
        if (! $result['success']) {
            // Issue paths without the leading "content"/"settings" (as Zod reports them per schema).
            $issues = array_map(fn ($i) => ['path' => array_slice($i['path'], 1), 'message' => $i['message']], $result['issues']);

            return ['ok' => false, 'reason' => 'INVALID', 'issues' => $issues];
        }

        return ['ok' => true, 'content' => $result['content'], 'settings' => $result['settings']];
    }

    /**
     * Validate every section of a draft. Hidden sections are checked only for type existence.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{sectionId: string, type: string, position: int, messages: list<string>}>
     */
    public static function validateDraftSections(array $rows): array
    {
        usort($rows, fn ($a, $b) => (int) $a['position'] <=> (int) $b['position']);
        $issues = [];
        $singletonSeen = [];
        foreach ($rows as $index => $row) {
            $position = $index + 1;
            $type = (string) $row['type'];
            $id = (string) $row['id'];
            if (! BlockRegistry::isBlockType($type)) {
                $issues[] = ['sectionId' => $id, 'type' => $type, 'position' => $position, 'messages' => [self::REASON_TEXT['UNKNOWN_TYPE']]];

                continue;
            }
            if (! ($row['isVisible'] ?? true)) {
                continue;
            }
            $def = BlockRegistry::definition($type);
            if (! empty($def['singleton'])) {
                if (isset($singletonSeen[$type])) {
                    $issues[] = ['sectionId' => $id, 'type' => $type, 'position' => $position, 'messages' => ["Only one “{$def['label']}” block is allowed per page"]];

                    continue;
                }
                $singletonSeen[$type] = true;
            }
            $parsed = self::parse($row);
            if (! $parsed['ok']) {
                $details = array_map(
                    fn ($i) => (implode('.', $i['path']) ?: 'block').': '.$i['message'],
                    array_slice($parsed['issues'], 0, 5),
                );
                $issues[] = ['sectionId' => $id, 'type' => $type, 'position' => $position, 'messages' => [self::REASON_TEXT[$parsed['reason']], ...$details]];
            }
        }

        return $issues;
    }

    /**
     * Validate the draft and build the immutable snapshot (visible sections, migrated to the current
     * block version with normalised content).
     *
     * @param  array{title: string, seoTitle?: ?string, seoDescription?: ?string, ogImageId?: ?string, noIndex?: bool, sections: list<array<string, mixed>>}  $input
     * @return array{ok: true, snapshot: stdClass, sectionCount: int}|array{ok: false, issues: list<array<string, mixed>>}
     */
    public static function prepare(array $input): array
    {
        $issues = self::validateDraftSections($input['sections']);
        if ($issues !== []) {
            return ['ok' => false, 'issues' => $issues];
        }
        $rows = $input['sections'];
        usort($rows, fn ($a, $b) => (int) $a['position'] <=> (int) $b['position']);
        $sections = [];
        foreach ($rows as $row) {
            if (! ($row['isVisible'] ?? true) || ! BlockRegistry::isBlockType((string) $row['type'])) {
                continue;
            }
            $parsed = self::parse($row);
            if (! $parsed['ok']) {
                return ['ok' => false, 'issues' => self::validateDraftSections($input['sections'])];
            }
            $sections[] = (object) [
                'id' => (string) $row['id'],
                'type' => (string) $row['type'],
                'schemaVersion' => BlockRegistry::version((string) $row['type']),
                'isVisible' => true,
                'content' => $parsed['content'],
                'settings' => $parsed['settings'] ?? new stdClass,
            ];
        }
        $snapshot = (object) [
            'title' => $input['title'],
            'seoTitle' => $input['seoTitle'] ?? null,
            'seoDescription' => $input['seoDescription'] ?? null,
            'ogImageId' => $input['ogImageId'] ?? null,
            'noIndex' => $input['noIndex'] ?? false,
            'sections' => $sections,
        ];

        return ['ok' => true, 'snapshot' => $snapshot, 'sectionCount' => count($sections)];
    }

    /** @param list<array{position: int, type: string, messages: list<string>}> $issues */
    public static function summarise(array $issues): string
    {
        if ($issues === []) {
            return '';
        }
        $first = $issues[0];
        $more = count($issues) > 1 ? ' (+'.(count($issues) - 1).' more)' : '';

        return "Section {$first['position']} · ".self::blockLabel($first['type']).': '.implode(' — ', $first['messages']).$more;
    }
}
