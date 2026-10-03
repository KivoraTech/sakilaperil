<?php

declare(strict_types=1);

namespace App\Blocks;

use App\Validation\JsonSchemas;
use Closure;
use RuntimeException;

/**
 * PHP view of the block registry (sakila/lib/blocks/registry.ts + parse.ts). Types, versions,
 * labels, defaults and content/settings schemas come from resources/schemas/blocks.json, exported
 * from the SAME Zod registry the admin uses, so they can never drift.
 *
 * Migrations: when a block's `version` goes above 1, register its PHP migration here in
 * self::MIGRATIONS (mirror of the TS `migrate`) — the export marks it with `hasMigrations`.
 */
final class BlockRegistry
{
    /**
     * PHP mirrors of the TS `migrate` functions, keyed by block type (none yet: every block is v1).
     *
     * @return array<string, Closure(int, array{content: mixed, settings: mixed}): array{content: mixed, settings: mixed}>
     */
    private static function migrations(): array
    {
        return [];
    }

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $types = null;

    /** @return array<string, array<string, mixed>> */
    public static function types(): array
    {
        if (self::$types === null) {
            /** @var array<string, array<string, mixed>> $types */
            $types = JsonSchemas::schema('blocks', 'types');
            self::$types = $types;
        }

        return self::$types;
    }

    /** @return list<string> */
    public static function typeNames(): array
    {
        return array_keys(self::types());
    }

    public static function isBlockType(mixed $type): bool
    {
        return is_string($type) && isset(self::types()[$type]);
    }

    /** @return array<string, mixed> */
    public static function definition(string $type): array
    {
        return self::types()[$type] ?? throw new RuntimeException("Unknown block type {$type}");
    }

    public static function version(string $type): int
    {
        return (int) self::definition($type)['version'];
    }

    /**
     * Upgrade stored data to the block's current version. Throws if a migration is missing.
     *
     * @param  array{content: mixed, settings: mixed}  $raw
     * @return array{content: mixed, settings: mixed}
     */
    public static function migrate(string $type, int $fromVersion, array $raw): array
    {
        $data = $raw;
        for ($v = $fromVersion; $v < self::version($type); $v++) {
            $migrate = self::migrations()[$type] ?? null;
            if ($migrate === null) {
                throw new RuntimeException("Block \"{$type}\" has no migration from v{$v}");
            }
            $data = $migrate($v, $data);
        }

        return $data;
    }

    /**
     * Validate + migrate one stored section (PageSection row or revision snapshot entry). Never throws.
     *
     * @param  array{id: string, type: string, schemaVersion: int, isVisible?: bool, content: mixed, settings?: mixed}  $section
     * @return array{ok: true, block: array{id: string, type: string, content: array<string, mixed>, settings: array<string, mixed>}}|array{ok: false, id: string, type: string, reason: string, issues?: list<array{path: list<string>, message: string}>}
     */
    public static function parseSection(array $section): array
    {
        $id = (string) $section['id'];
        $type = (string) $section['type'];
        if (! self::isBlockType($type)) {
            return ['ok' => false, 'id' => $id, 'type' => $type, 'reason' => 'UNKNOWN_TYPE'];
        }
        if ((int) $section['schemaVersion'] > self::version($type)) {
            return ['ok' => false, 'id' => $id, 'type' => $type, 'reason' => 'FUTURE_VERSION'];
        }
        try {
            $raw = self::migrate($type, (int) $section['schemaVersion'], [
                'content' => $section['content'],
                'settings' => $section['settings'] ?? [],
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'id' => $id, 'type' => $type, 'reason' => 'MIGRATION_FAILED'];
        }
        $content = JsonSchemas::validate('blocks', "types.{$type}.content", $raw['content']);
        $settings = JsonSchemas::validate('blocks', "types.{$type}.settings", $raw['settings'] ?? []);
        if (! $content->ok || ! $settings->ok) {
            return ['ok' => false, 'id' => $id, 'type' => $type, 'reason' => 'INVALID', 'issues' => [...$content->issues, ...$settings->issues]];
        }

        return ['ok' => true, 'block' => [
            'id' => $id,
            'type' => $type,
            'content' => (array) $content->data(),
            'settings' => (array) $settings->data(),
        ]];
    }

    /**
     * For rendering: hidden sections are dropped, invalid ones reported in `errors` (skipped publicly).
     *
     * @param  iterable<array<string, mixed>>  $sections
     * @return array{blocks: list<array{id: string, type: string, content: array<string, mixed>, settings: array<string, mixed>}>, errors: list<array<string, mixed>>}
     */
    public static function parseSections(iterable $sections): array
    {
        $blocks = [];
        $errors = [];
        foreach ($sections as $section) {
            if (($section['isVisible'] ?? true) === false) {
                continue;
            }
            $result = self::parseSection([
                'id' => (string) ($section['id'] ?? ''),
                'type' => (string) ($section['type'] ?? ''),
                'schemaVersion' => (int) ($section['schemaVersion'] ?? 1),
                'content' => $section['content'] ?? null,
                'settings' => $section['settings'] ?? [],
            ]);
            if ($result['ok']) {
                $blocks[] = $result['block'];
            } else {
                $errors[] = $result;
            }
        }

        return ['blocks' => $blocks, 'errors' => $errors];
    }

    /**
     * Validate content+settings for SAVE/PUBLISH. Issues carry builder-friendly paths
     * (["content", …] / ["settings", …]). On success returns Zod-output-shaped data (stdClass trees
     * for storage with JsonSchemas::encode) and the current version.
     *
     * @return array{success: true, content: mixed, settings: mixed, version: int}|array{success: false, issues: list<array{path: list<string>, message: string}>}
     */
    public static function validateInput(string $type, mixed $content, mixed $settings): array
    {
        if (! self::isBlockType($type)) {
            return ['success' => false, 'issues' => [['path' => ['type'], 'message' => 'Unknown block type']]];
        }
        $c = JsonSchemas::validate('blocks', "types.{$type}.content", $content);
        $s = JsonSchemas::validate('blocks', "types.{$type}.settings", $settings ?? []);
        if ($c->ok && $s->ok) {
            return ['success' => true, 'content' => $c->object, 'settings' => $s->object, 'version' => self::version($type)];
        }
        $issues = [];
        foreach ($c->issues as $i) {
            $issues[] = ['path' => ['content', ...$i['path']], 'message' => $i['message']];
        }
        foreach ($s->issues as $i) {
            $issues[] = ['path' => ['settings', ...$i['path']], 'message' => $i['message']];
        }

        return ['success' => false, 'issues' => $issues];
    }

    /**
     * Walk any JSON value and collect every `assetId` string (for batched media resolution).
     *
     * @param  array<string, true>  $into
     * @return array<string, true>
     */
    public static function collectAssetIds(mixed $value, array $into = []): array
    {
        if ($value instanceof \stdClass) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if ($k === 'assetId' && is_string($v) && $v !== '') {
                    $into[$v] = true;
                } else {
                    $into = self::collectAssetIds($v, $into);
                }
            }
        }

        return $into;
    }
}
