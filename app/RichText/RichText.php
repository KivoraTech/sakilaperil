<?php

declare(strict_types=1);

namespace App\RichText;

use App\I18n\Locales;

/**
 * Tiptap / ProseMirror JSON rich text — port of sakila/lib/richtext/{schema,render}.tsx.
 *
 * - sanitize(): STRICT allow-list pass. Unknown nodes/marks/attrs are dropped, links must match
 *   SAFE_HREF, videos must be valid YouTube/Vimeo ids, images must point at our uploads or https.
 *   Admin actions run it before saving; render() runs it again (defence in depth).
 * - render(): SAFE renderer, Tiptap JSON → HTML string. Byte-for-byte the markup React's
 *   renderToStaticMarkup produces for <RichText> in the reference (same tags, attribute order,
 *   self-closing void elements, inline styles and React's text escaping). All text and attribute
 *   values are escaped; there is no path for raw HTML.
 *
 * Public usage (Blade):  {!! \App\RichText\RichText::render($body, $locale, $assets, 'prose-sakila') !!}
 * `$assets` = Assets::byIds(RichText::collectAssetIds($body), $locale) so image nodes pick up the
 * CURRENT url/alt/size of a MediaAsset.
 */
final class RichText
{
    public const ALLOWED_NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem',
        'blockquote', 'hardBreak', 'horizontalRule', 'image', 'videoEmbed',
    ];

    public const ALLOWED_MARKS = ['bold', 'italic', 'underline', 'strike', 'link'];

    /** Headings inside body text start at h2 (the page title is the h1). */
    public const HEADING_LEVELS = [2, 3, 4];

    /** Same as SAFE_HREF in resources/js/lib/blocks/fields.ts. */
    public const SAFE_HREF = '~^(/(?![/\\\\])[^\s\\\\]*|#[A-Za-z][\w-]*|https://[^\s]+|mailto:[^\s]+|tel:\+?[\d\s()-]+)$~Du';

    private const MAX_DEPTH = 12;

    private const MAX_NODES = 5000;

    /** @return array{type: string, content: list<array<string, mixed>>} */
    public static function emptyDoc(): array
    {
        return ['type' => 'doc', 'content' => []];
    }

    /**
     * @param  list<string>  $paragraphs
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    public static function docFromParagraphs(array $paragraphs): array
    {
        return ['type' => 'doc', 'content' => array_map(
            fn (string $p): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $p]]],
            $paragraphs,
        )];
    }

    /** Image sources we render: our own uploads (local route) or https (object storage / CDN). */
    public static function isSafeImageSrc(mixed $src): bool
    {
        if (! is_string($src) || strlen($src) > 1000) {
            return false;
        }
        if (preg_match('/[\s\\\\"\'<>]/u', $src) === 1) {
            return false;
        }

        return preg_match('~^/uploads/[A-Za-z0-9/_.-]+$~D', $src) === 1 || preg_match('~^https://[^/]+/~', $src) === 1;
    }

    public static function isSafeHref(mixed $href): bool
    {
        return is_string($href) && mb_strlen($href) <= 500 && preg_match(self::SAFE_HREF, self::jsTrim($href)) === 1;
    }

    /**
     * Strict allow-list sanitisation. Accepts anything (decoded JSON from the DB or the client)
     * and never throws. The result contains only allowed nodes, marks and attributes.
     *
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    public static function sanitize(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($input) || ($input['type'] ?? null) !== 'doc') {
            return self::emptyDoc();
        }
        $counter = 0;
        $content = [];
        if (isset($input['content']) && is_array($input['content']) && array_is_list($input['content'])) {
            foreach ($input['content'] as $child) {
                $clean = self::cleanNode($child, 1, $counter);
                if ($clean !== null) {
                    $content[] = $clean;
                }
            }
        }

        return ['type' => 'doc', 'content' => $content];
    }

    /** @return array<string, mixed>|null */
    private static function cleanNode(mixed $raw, int $depth, int &$counter): ?array
    {
        if (! is_array($raw) || $depth > self::MAX_DEPTH) {
            return null;
        }
        if (++$counter > self::MAX_NODES) {
            return null;
        }
        $type = $raw['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::ALLOWED_NODES, true) || $type === 'doc') {
            return null;
        }
        $attrs = isset($raw['attrs']) && is_array($raw['attrs']) ? $raw['attrs'] : [];
        $children = function () use ($raw, $depth, &$counter): array {
            $out = [];
            if (isset($raw['content']) && is_array($raw['content']) && array_is_list($raw['content'])) {
                foreach ($raw['content'] as $c) {
                    $clean = self::cleanNode($c, $depth + 1, $counter);
                    if ($clean !== null) {
                        $out[] = $clean;
                    }
                }
            }

            return $out;
        };

        switch ($type) {
            case 'text':
                $text = $raw['text'] ?? null;
                if (! is_string($text) || $text === '') {
                    return null;
                }
                $marks = self::cleanMarks($raw['marks'] ?? null);
                $node = ['type' => 'text', 'text' => mb_substr($text, 0, 20000)];
                if ($marks !== null) {
                    $node['marks'] = $marks;
                }

                return $node;
            case 'hardBreak':
            case 'horizontalRule':
                return ['type' => $type];
            case 'heading':
                $lvl = is_numeric($attrs['level'] ?? null) ? (float) $attrs['level'] : NAN;
                $level = in_array($lvl, [2.0, 3.0, 4.0], true) ? (int) $lvl : ($lvl > 4 ? 4 : 2);

                return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => $children()];
            case 'orderedList':
                $start = self::posInt($attrs['start'] ?? null, 10000) ?? 1;

                return [
                    'type' => 'orderedList',
                    'attrs' => ['start' => $start],
                    'content' => array_values(array_filter($children(), fn (array $c): bool => $c['type'] === 'listItem')),
                ];
            case 'bulletList':
                return [
                    'type' => 'bulletList',
                    'content' => array_values(array_filter($children(), fn (array $c): bool => $c['type'] === 'listItem')),
                ];
            case 'image':
                $src = $attrs['src'] ?? null;
                if (! self::isSafeImageSrc($src)) {
                    return null;
                }
                $out = ['src' => $src, 'alt' => self::str($attrs['alt'] ?? null, 300) ?? ''];
                $assetId = self::str($attrs['assetId'] ?? null, 64);
                if ($assetId !== null) {
                    $out['assetId'] = $assetId;
                }
                if (self::posInt($attrs['width'] ?? null, 20000) !== null) {
                    $out['width'] = self::posInt($attrs['width'], 20000);
                }
                if (self::posInt($attrs['height'] ?? null, 20000) !== null) {
                    $out['height'] = self::posInt($attrs['height'], 20000);
                }
                $caption = self::str($attrs['caption'] ?? null, 300);
                if ($caption !== null) {
                    $out['caption'] = $caption;
                }

                return ['type' => 'image', 'attrs' => $out];
            case 'videoEmbed':
                $provider = $attrs['provider'] ?? null;
                $videoId = $attrs['videoId'] ?? null;
                if (! Video::isValidId($provider, $videoId)) {
                    return null;
                }
                $out = ['provider' => $provider, 'videoId' => $videoId];
                $title = self::str($attrs['title'] ?? null, 200);
                if ($title !== null) {
                    $out['title'] = $title;
                }

                return ['type' => 'videoEmbed', 'attrs' => $out];
            default:
                // paragraph, listItem, blockquote
                return ['type' => $type, 'content' => $children()];
        }
    }

    /** @return list<array<string, mixed>>|null */
    private static function cleanMarks(mixed $marks): ?array
    {
        if (! is_array($marks) || ! array_is_list($marks)) {
            return null;
        }
        $out = [];
        $seen = [];
        foreach (array_slice($marks, 0, 20) as $m) {
            $type = is_array($m) ? ($m['type'] ?? null) : null;
            if (! is_string($type) || ! in_array($type, self::ALLOWED_MARKS, true) || isset($seen[$type])) {
                continue;
            }
            if ($type === 'link') {
                $href = is_array($m['attrs'] ?? null) ? ($m['attrs']['href'] ?? null) : null;
                if (! self::isSafeHref($href)) {
                    continue; // unsafe link → the text survives without the link
                }
                $out[] = ['type' => 'link', 'attrs' => ['href' => self::jsTrim((string) $href)]];
            } else {
                $out[] = ['type' => $type];
            }
            $seen[$type] = true;
        }

        return $out !== [] ? $out : null;
    }

    private static function str(mixed $v, int $max): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $t = self::jsTrim($v);

        return $t !== '' ? mb_substr($t, 0, $max) : null;
    }

    private static function posInt(mixed $v, int $max): ?int
    {
        if (is_int($v)) {
            return $v > 0 && $v <= $max ? $v : null;
        }
        if (is_float($v) && is_finite($v) && floor($v) === $v) {
            return $v > 0 && $v <= $max ? (int) $v : null;
        }

        return null;
    }

    /** String.prototype.trim (Unicode whitespace), not PHP's ASCII trim(). */
    private static function jsTrim(string $s): string
    {
        return (string) preg_replace('/^[\s\x{FEFF}\x{00A0}]+|[\s\x{FEFF}\x{00A0}]+$/u', '', $s);
    }

    // ───────────────────────── Helpers ─────────────────────────

    /** True when the document has no visible content. */
    public static function isEmpty(mixed $doc): bool
    {
        return trim(self::toPlainText($doc)) === '' && self::collectMedia($doc) === [];
    }

    /** Plain text (for excerpts, search, meta descriptions). */
    public static function toPlainText(mixed $doc, int $maxLength = 100000): string
    {
        $out = [];
        $walk = function (mixed $n) use (&$walk, &$out): void {
            if (! is_array($n)) {
                return;
            }
            $type = $n['type'] ?? null;
            if ($type === 'text' && is_string($n['text'] ?? null)) {
                $out[] = $n['text'];
            }
            if ($type === 'hardBreak') {
                $out[] = ' ';
            }
            if (isset($n['content']) && is_array($n['content'])) {
                foreach ($n['content'] as $c) {
                    $walk($c);
                }
                if ($type !== 'text' && $type !== 'doc') {
                    $out[] = ' ';
                }
            }
        };
        $walk(is_string($doc) ? json_decode($doc, true) : $doc);

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', implode('', $out))), 0, $maxLength);
    }

    /**
     * Media asset ids + video embeds referenced by a doc.
     *
     * @return list<array{type: string, id: string}>
     */
    public static function collectMedia(mixed $doc): array
    {
        $out = [];
        $walk = function (mixed $n) use (&$walk, &$out): void {
            if (! is_array($n)) {
                return;
            }
            $type = $n['type'] ?? null;
            $attrs = is_array($n['attrs'] ?? null) ? $n['attrs'] : [];
            if ($type === 'image' && is_string($attrs['assetId'] ?? null)) {
                $out[] = ['type' => 'image', 'id' => $attrs['assetId']];
            }
            if ($type === 'videoEmbed' && is_string($attrs['videoId'] ?? null)) {
                $out[] = ['type' => 'videoEmbed', 'id' => $attrs['videoId']];
            }
            if (isset($n['content']) && is_array($n['content'])) {
                foreach ($n['content'] as $c) {
                    $walk($c);
                }
            }
        };
        $walk(is_string($doc) ? json_decode($doc, true) : $doc);

        return $out;
    }

    /**
     * Asset ids used by image nodes (unique, in document order).
     *
     * @return list<string>
     */
    public static function collectAssetIds(mixed $doc): array
    {
        $ids = [];
        foreach (self::collectMedia($doc) as $m) {
            if ($m['type'] === 'image') {
                $ids[$m['id']] = true;
            }
        }

        return array_keys($ids);
    }

    // ───────────────────────── Renderer ─────────────────────────

    /**
     * SAFE renderer → HTML string ('' for an empty document, like the React component's null).
     *
     * @param  array<string, array{url: string, width: ?int, height: ?int, alt: string}>  $assets
     */
    public static function render(mixed $doc, ?string $locale = null, array $assets = [], ?string $className = null): string
    {
        $clean = self::sanitize($doc);
        if ($clean['content'] === []) {
            return '';
        }
        $locale = Locales::isLocale($locale) ? $locale : null;
        $class = $className !== null ? ' class="'.self::e($className).'"' : '';

        return '<div'.$class.'>'.self::renderNodes($clean['content'], $locale, $assets).'</div>';
    }

    /**
     * Resolve an href for rendering: prefix the locale on internal paths; mark external links.
     *
     * @return array{href: string, external: bool}
     */
    public static function resolveHref(string $href, ?string $locale = null): array
    {
        if (str_starts_with($href, '/')) {
            $first = preg_split('~[/?#]~', $href)[1] ?? '';
            $skip = Locales::isLocale($first) || $first === 'uploads' || $first === 'api' || $locale === null;

            return ['href' => $skip ? $href : '/'.$locale.($href === '/' ? '' : $href), 'external' => false];
        }

        return ['href' => $href, 'external' => preg_match('/^https:/i', $href) === 1];
    }

    /**
     * @param  list<array<string, mixed>>|null  $nodes
     * @param  array<string, array{url: string, width: ?int, height: ?int, alt: string}>  $assets
     */
    private static function renderNodes(?array $nodes, ?string $locale, array $assets): string
    {
        $html = '';
        foreach ($nodes ?? [] as $n) {
            $html .= self::renderNode($n, $locale, $assets);
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{url: string, width: ?int, height: ?int, alt: string}>  $assets
     */
    private static function renderNode(array $node, ?string $locale, array $assets): string
    {
        $kids = fn (): string => self::renderNodes($node['content'] ?? null, $locale, $assets);
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

        switch ($node['type']) {
            case 'text':
                return self::renderText($node, $locale);
            case 'paragraph':
                return '<p>'.$kids().'</p>';
            case 'heading':
                $level = $attrs['level'] ?? 2;
                $tag = $level === 3 ? 'h3' : ($level === 4 ? 'h4' : 'h2');

                return "<{$tag}>".$kids()."</{$tag}>";
            case 'bulletList':
                return '<ul>'.$kids().'</ul>';
            case 'orderedList':
                $start = is_int($attrs['start'] ?? null) ? $attrs['start'] : 1;

                return ($start === 1 ? '<ol>' : '<ol start="'.$start.'">').$kids().'</ol>';
            case 'listItem':
                return '<li>'.$kids().'</li>';
            case 'blockquote':
                return '<blockquote>'.$kids().'</blockquote>';
            case 'hardBreak':
                return '<br/>';
            case 'horizontalRule':
                return '<hr/>';
            case 'image':
                $asset = is_string($attrs['assetId'] ?? null) ? ($assets[$attrs['assetId']] ?? null) : null;
                $src = $asset['url'] ?? (string) ($attrs['src'] ?? '');
                $alt = ($asset['alt'] ?? '') !== '' ? (string) $asset['alt'] : (is_string($attrs['alt'] ?? null) ? $attrs['alt'] : '');
                $width = $asset['width'] ?? (is_int($attrs['width'] ?? null) ? $attrs['width'] : null);
                $height = $asset['height'] ?? (is_int($attrs['height'] ?? null) ? $attrs['height'] : null);
                $caption = is_string($attrs['caption'] ?? null) ? $attrs['caption'] : null;

                return '<figure><img src="'.self::e($src).'" alt="'.self::e($alt).'"'
                    .($width !== null ? ' width="'.(int) $width.'"' : '')
                    .($height !== null ? ' height="'.(int) $height.'"' : '')
                    .' loading="lazy" decoding="async"/>'
                    .($caption !== null && $caption !== '' ? '<figcaption>'.self::e($caption).'</figcaption>' : '')
                    .'</figure>';
            case 'videoEmbed':
                $provider = (string) ($attrs['provider'] ?? '');
                $id = (string) ($attrs['videoId'] ?? '');
                $title = is_string($attrs['title'] ?? null) && $attrs['title'] !== ''
                    ? $attrs['title']
                    : ($provider === 'youtube' ? 'YouTube video' : 'Vimeo video');

                return '<div class="rt-video" style="position:relative;aspect-ratio:16 / 9">'
                    .'<iframe src="'.self::e(Video::embedUrl($provider, $id)).'" title="'.self::e($title).'"'
                    .' loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"'
                    .' allowFullScreen="" referrerPolicy="strict-origin-when-cross-origin"'
                    .' sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"'
                    .' style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe></div>';
            default:
                return '';
        }
    }

    /** @param  array<string, mixed>  $node */
    private static function renderText(array $node, ?string $locale): string
    {
        $el = self::e((string) ($node['text'] ?? ''));
        $marks = is_array($node['marks'] ?? null) ? $node['marks'] : [];
        // Formatting marks first, the link last so it wraps the formatted text.
        foreach ($marks as $m) {
            $el = match ($m['type'] ?? null) {
                'bold' => '<strong>'.$el.'</strong>',
                'italic' => '<em>'.$el.'</em>',
                'underline' => '<u>'.$el.'</u>',
                'strike' => '<s>'.$el.'</s>',
                default => $el,
            };
        }
        foreach ($marks as $m) {
            if (($m['type'] ?? null) === 'link' && is_string($m['attrs']['href'] ?? null)) {
                $r = self::resolveHref($m['attrs']['href'], $locale);
                $el = $r['external']
                    ? '<a href="'.self::e($r['href']).'" rel="noopener noreferrer nofollow">'.$el.'</a>'
                    : '<a href="'.self::e($r['href']).'">'.$el.'</a>';
                break;
            }
        }

        return $el;
    }

    /** React's escapeTextForBrowser (same entities, so the HTML matches the reference byte for byte). */
    public static function e(string $s): string
    {
        return str_replace(['&', '<', '>', '"', "'"], ['&amp;', '&lt;', '&gt;', '&quot;', '&#x27;'], $s);
    }
}
