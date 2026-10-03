<?php

declare(strict_types=1);

namespace App\Content\Real;

/**
 * Tiny Markdown-like → Tiptap document builder for the content dataset (database/content/*.php):
 * blocks separated by blank lines; `## ` / `### ` headings; `- ` bullet lists; `1. ` numbered
 * lists; inline `**bold**`, `*italic*` and `[label](href)`. Everything else is plain text. The
 * result still goes through the CMS's own rich-text sanitiser when it is saved.
 */
final class Rt
{
    /** @return array{type: 'doc', content: list<array<string, mixed>>} */
    public static function doc(string $source): array
    {
        $blocks = preg_split('/\n\s*\n/', trim(str_replace("\r\n", "\n", $source))) ?: [];
        $content = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            if (preg_match('/^(#{2,4}) +(.+)$/s', $block, $m) === 1) {
                $content[] = ['type' => 'heading', 'attrs' => ['level' => strlen($m[1])], 'content' => self::inline(self::oneLine($m[2]))];

                continue;
            }
            $lines = explode("\n", $block);
            if (self::all($lines, '/^- +/')) {
                $content[] = ['type' => 'bulletList', 'content' => self::items($lines, '/^- +/')];

                continue;
            }
            if (self::all($lines, '/^\d+\. +/')) {
                $content[] = ['type' => 'orderedList', 'content' => self::items($lines, '/^\d+\. +/')];

                continue;
            }
            $content[] = ['type' => 'paragraph', 'content' => self::inline(self::oneLine($block))];
        }

        return ['type' => 'doc', 'content' => $content];
    }

    private static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/\s*\n\s*/', ' ', $text));
    }

    /** @param list<string> $lines */
    private static function all(array $lines, string $pattern): bool
    {
        foreach ($lines as $line) {
            if (preg_match($pattern, ltrim($line)) !== 1) {
                return false;
            }
        }

        return $lines !== [];
    }

    /**
     * @param  list<string>  $lines
     * @return list<array<string, mixed>>
     */
    private static function items(array $lines, string $pattern): array
    {
        $out = [];
        foreach ($lines as $line) {
            $text = (string) preg_replace($pattern, '', ltrim($line));
            $out[] = ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => self::inline(trim($text))]]];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<array<string, mixed>>
     */
    private static function inline(string $text, array $marks = []): array
    {
        $nodes = [];
        $pattern = '/\[([^\]]+)\]\(([^)\s]+)\)|\*\*(.+?)\*\*|\*(.+?)\*/s';
        $offset = 0;
        while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = (int) $m[0][1];
            if ($start > $offset) {
                $nodes[] = self::text(substr($text, $offset, $start - $offset), $marks);
            }
            // Unmatched groups are reported as ['', -1] (or are missing when they come last).
            $group = fn (int $i): ?string => isset($m[$i]) && $m[$i][1] >= 0 && $m[$i][0] !== '' ? $m[$i][0] : null;
            if (($label = $group(1)) !== null) {
                $nodes = [...$nodes, ...self::inline($label, [...$marks, ['type' => 'link', 'attrs' => ['href' => (string) $group(2)]]])];
            } elseif (($bold = $group(3)) !== null) {
                $nodes = [...$nodes, ...self::inline($bold, [...$marks, ['type' => 'bold']])];
            } elseif (($italic = $group(4)) !== null) {
                $nodes = [...$nodes, ...self::inline($italic, [...$marks, ['type' => 'italic']])];
            }
            $offset = $start + strlen($m[0][0]);
        }
        if ($offset < strlen($text)) {
            $nodes[] = self::text(substr($text, $offset), $marks);
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return array<string, mixed>
     */
    private static function text(string $text, array $marks): array
    {
        return $marks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks];
    }
}
