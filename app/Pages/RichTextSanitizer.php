<?php

declare(strict_types=1);

namespace App\Pages;

use stdClass;

/**
 * Strict allow-list pass over a Tiptap/ProseMirror document — port of sanitizeRichText() in
 * resources/js/lib/richtext/schema.ts. Unknown nodes/marks/attrs are dropped, links must match
 * SAFE_HREF, videos must be valid YouTube/Vimeo ids, images must point at our uploads or https.
 * Accepts anything and never throws. Used before draft sections are stored (defence in depth).
 */
final class RichTextSanitizer
{
    public const SAFE_HREF = '~^(/(?![/\\\\])[^\s\\\\]*|#[A-Za-z][\w-]*|https://[^\s]+|mailto:[^\s]+|tel:\+?[\d\s()-]+)$~';

    private const NODES = ['paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'hardBreak', 'horizontalRule', 'image', 'videoEmbed'];

    private const MARKS = ['bold', 'italic', 'underline', 'strike', 'link'];

    private const MAX_DEPTH = 12;

    private const MAX_NODES = 5000;

    private int $count = 0;

    public static function sanitize(mixed $input): stdClass
    {
        $doc = new stdClass;
        $doc->type = 'doc';
        $doc->content = [];
        $input = self::obj($input);
        if ($input === null || ($input['type'] ?? null) !== 'doc') {
            return $doc;
        }
        $self = new self;
        if (is_array($input['content'] ?? null)) {
            foreach ($input['content'] as $child) {
                $clean = $self->node($child, 1);
                if ($clean !== null) {
                    $doc->content[] = $clean;
                }
            }
        }

        return $doc;
    }

    /** Walk any block JSON and sanitise every embedded `{ type: "doc" }` (reference sanitizeBlockJson). */
    public static function sanitizeBlockJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeBlockJson'], $value);
        }
        if ($value instanceof stdClass) {
            if (($value->type ?? null) === 'doc') {
                return self::sanitize($value);
            }
            $out = new stdClass;
            foreach (get_object_vars($value) as $k => $v) {
                $out->{$k} = self::sanitizeBlockJson($v);
            }

            return $out;
        }

        return $value;
    }

    public static function isSafeHref(mixed $href): bool
    {
        return is_string($href) && mb_strlen($href) <= 500 && preg_match(self::SAFE_HREF, trim($href)) === 1;
    }

    public static function isSafeImageSrc(mixed $src): bool
    {
        if (! is_string($src) || strlen($src) > 1000 || preg_match('/[\s\\\\"\'<>]/', $src) === 1) {
            return false;
        }

        return preg_match('~^/uploads/[A-Za-z0-9/_.-]+$~', $src) === 1 || preg_match('~^https://[^/]+/~', $src) === 1;
    }

    public static function isValidVideoId(mixed $provider, mixed $id): bool
    {
        if (! is_string($id)) {
            return false;
        }

        return match ($provider) {
            'youtube' => preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1,
            'vimeo' => preg_match('/^\d{1,12}$/', $id) === 1,
            default => false,
        };
    }

    /** @return array<string, mixed>|null */
    private static function obj(mixed $v): ?array
    {
        if ($v instanceof stdClass) {
            return get_object_vars($v);
        }

        return is_array($v) && ! array_is_list($v) ? $v : null;
    }

    private static function str(mixed $v, int $max): ?string
    {
        return is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
    }

    private static function posInt(mixed $v, int $max): ?int
    {
        return is_int($v) && $v > 0 && $v <= $max ? $v : null;
    }

    private function node(mixed $raw, int $depth): ?stdClass
    {
        $n = self::obj($raw);
        if ($n === null || $depth > self::MAX_DEPTH) {
            return null;
        }
        if (++$this->count > self::MAX_NODES) {
            return null;
        }
        $type = $n['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::NODES, true)) {
            return null;
        }
        $attrs = self::obj($n['attrs'] ?? null) ?? [];
        $children = function () use ($n, $depth): array {
            $out = [];
            foreach (is_array($n['content'] ?? null) ? $n['content'] : [] as $c) {
                $clean = $this->node($c, $depth + 1);
                if ($clean !== null) {
                    $out[] = $clean;
                }
            }

            return $out;
        };
        $o = new stdClass;
        $o->type = $type;

        switch ($type) {
            case 'text':
                $text = $n['text'] ?? null;
                if (! is_string($text) || $text === '') {
                    return null;
                }
                $o->text = mb_substr($text, 0, 20000);
                $marks = $this->marks($n['marks'] ?? null);
                if ($marks !== null) {
                    $o->marks = $marks;
                }

                return $o;
            case 'hardBreak':
            case 'horizontalRule':
                return $o;
            case 'heading':
                $lvl = is_numeric($attrs['level'] ?? null) ? (float) $attrs['level'] : NAN;
                $level = in_array($lvl, [2.0, 3.0, 4.0], true) ? (int) $lvl : ($lvl > 4 ? 4 : 2);
                $o->attrs = (object) ['level' => $level];
                $o->content = $children();

                return $o;
            case 'orderedList':
                $o->attrs = (object) ['start' => self::posInt($attrs['start'] ?? null, 10000) ?? 1];
                $o->content = array_values(array_filter($children(), fn ($c) => $c->type === 'listItem'));

                return $o;
            case 'bulletList':
                $o->content = array_values(array_filter($children(), fn ($c) => $c->type === 'listItem'));

                return $o;
            case 'image':
                $src = $attrs['src'] ?? null;
                if (! self::isSafeImageSrc($src)) {
                    return null;
                }
                $a = ['src' => $src, 'alt' => self::str($attrs['alt'] ?? null, 300) ?? ''];
                if (($assetId = self::str($attrs['assetId'] ?? null, 64)) !== null) {
                    $a['assetId'] = $assetId;
                }
                if (self::posInt($attrs['width'] ?? null, 20000) !== null) {
                    $a['width'] = $attrs['width'];
                }
                if (self::posInt($attrs['height'] ?? null, 20000) !== null) {
                    $a['height'] = $attrs['height'];
                }
                if (($caption = self::str($attrs['caption'] ?? null, 300)) !== null) {
                    $a['caption'] = $caption;
                }
                $o->attrs = (object) $a;

                return $o;
            case 'videoEmbed':
                $provider = $attrs['provider'] ?? null;
                $videoId = $attrs['videoId'] ?? null;
                if (! self::isValidVideoId($provider, $videoId)) {
                    return null;
                }
                $a = ['provider' => $provider, 'videoId' => $videoId];
                if (($title = self::str($attrs['title'] ?? null, 200)) !== null) {
                    $a['title'] = $title;
                }
                $o->attrs = (object) $a;

                return $o;
            default:
                $o->content = $children();

                return $o;
        }
    }

    /** @return list<stdClass>|null */
    private function marks(mixed $marks): ?array
    {
        if (! is_array($marks)) {
            return null;
        }
        $out = [];
        $seen = [];
        foreach (array_slice($marks, 0, 20) as $raw) {
            $m = self::obj($raw);
            $type = $m['type'] ?? null;
            if ($m === null || ! is_string($type) || ! in_array($type, self::MARKS, true) || isset($seen[$type])) {
                continue;
            }
            if ($type === 'link') {
                $href = self::obj($m['attrs'] ?? null)['href'] ?? null;
                if (! self::isSafeHref($href)) {
                    continue;
                }
                $out[] = (object) ['type' => 'link', 'attrs' => (object) ['href' => trim((string) $href)]];
            } else {
                $out[] = (object) ['type' => $type];
            }
            $seen[$type] = true;
        }

        return $out === [] ? null : $out;
    }
}
