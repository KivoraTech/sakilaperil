<?php

declare(strict_types=1);

namespace App\View;

use Illuminate\Support\HtmlString;

/**
 * Port of components/site/ui/rich-text.tsx — the renderer the reference PUBLIC pages actually use
 * (blocks, project/news/event bodies): Tiptap JSON → allow-listed elements, every text run wrapped
 * in a <span> (as React rendered it), "[[TODO: …]]" markers, safe links through SmartLink. Unknown
 * nodes render their children inside a <span>; unknown marks are ignored. Text is always escaped.
 */
final class SiteRichText
{
    /** RichTextContent: the `prose-site` wrapper, or nothing when the document is empty. */
    public static function content(mixed $doc, ?string $class = null): HtmlString
    {
        $nodes = self::children($doc);
        if ($nodes === []) {
            return new HtmlString('');
        }

        return new HtmlString('<div class="'.e(Html::cx('prose-site', $class)).'">'.self::renderNodes($nodes).'</div>');
    }

    public static function countWords(mixed $doc): int
    {
        $n = 0;
        $walk = function (array $nodes) use (&$walk, &$n): void {
            foreach ($nodes as $x) {
                if (! is_array($x)) {
                    continue;
                }
                if (isset($x['text']) && is_string($x['text'])) {
                    $words = preg_split('/\s+/', trim($x['text'])) ?: [];
                    $n += count(array_filter($words, static fn (string $w): bool => $w !== ''));
                }
                $walk(self::children($x));
            }
        };
        $walk(self::children($doc));

        return $n;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function children(mixed $node): array
    {
        if (! is_array($node) || ! isset($node['content']) || ! is_array($node['content'])) {
            return [];
        }

        return array_values(array_filter($node['content'], 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function renderNodes(array $nodes): string
    {
        return implode('', array_map(self::renderNode(...), $nodes));
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private static function renderNode(array $n): string
    {
        $kids = fn (): string => self::renderNodes(self::children($n));

        return match ($n['type'] ?? null) {
            'text' => self::renderText($n),
            'paragraph' => '<p>'.$kids().'</p>',
            'heading' => match (true) {
                (int) ($n['attrs']['level'] ?? 2) <= 2 => '<h2>'.$kids().'</h2>',
                (int) $n['attrs']['level'] === 3 => '<h3>'.$kids().'</h3>',
                default => '<h4>'.$kids().'</h4>',
            },
            'bulletList' => '<ul>'.$kids().'</ul>',
            'orderedList' => '<ol>'.$kids().'</ol>',
            'listItem' => '<li>'.$kids().'</li>',
            'blockquote' => '<blockquote>'.$kids().'</blockquote>',
            'hardBreak' => '<br>',
            'horizontalRule' => '<hr>',
            default => '<span>'.$kids().'</span>',
        };
    }

    /**
     * @param  array<string, mixed>  $n
     */
    private static function renderText(array $n): string
    {
        $html = Ui::todoText(is_string($n['text'] ?? null) ? $n['text'] : '')->toHtml();
        foreach (is_array($n['marks'] ?? null) ? $n['marks'] : [] as $m) {
            $type = is_array($m) ? ($m['type'] ?? null) : null;
            $html = match ($type) {
                'bold' => '<strong>'.$html.'</strong>',
                'italic' => '<em>'.$html.'</em>',
                'underline' => '<u>'.$html.'</u>',
                'strike' => '<s>'.$html.'</s>',
                'code' => '<code class="rounded bg-cream px-1 font-mono text-[0.9em]">'.$html.'</code>',
                'link' => self::link($m, $html),
                default => $html,
            };
        }

        return '<span>'.$html.'</span>';
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private static function link(array $mark, string $html): string
    {
        $href = is_string($mark['attrs']['href'] ?? null) ? trim($mark['attrs']['href']) : '';
        if ($href === '' || preg_match(Ui::SAFE_HREF, $href) !== 1) {
            return $html;
        }

        return Ui::smartLink($href, new HtmlString($html))->toHtml();
    }
}
