<?php

declare(strict_types=1);

namespace App\View;

use App\Blocks\BlockRegistry;
use App\Media\Assets;
use Illuminate\Support\HtmlString;

/**
 * Port of components/site/blocks/render-blocks.tsx (contract PLAN.md §5.3):
 * 1. validate type  2. migrate + validate data  3. resolve media in ONE query
 * 4. render the registered Blade view (resources/views/site/blocks/<type>.blade.php)
 * 5. fail safely — invalid blocks are skipped publicly (logged) and shown as a warning in preview.
 *
 * Usage (public pages and the ADMIN preview):
 *   {{ \App\View\Blocks::render($page['sections'], $locale) }}
 *   {{ \App\View\Blocks::render($draftSections, $locale, isPreview: true) }}
 *
 * Each block view receives `$block` ({id, type, content, settings}) and `$context`
 * ({locale, assets, isPreview, index}).
 */
final class Blocks
{
    /** Type of the last block rendered into the current page (the footer reads it once). */
    private static ?string $lastBlockType = null;

    /**
     * True when the page's content ends with a newsletter sign-up, so the footer does not put a
     * second sign-up form right below it. Resets itself (one process may serve many requests).
     */
    public static function consumeEndsWithNewsletter(): bool
    {
        $ends = self::$lastBlockType === 'newsletter';
        self::$lastBlockType = null;

        return $ends;
    }

    /** For pages whose own content ends with a newsletter form that is not a block. */
    public static function markEndsWithNewsletter(): void
    {
        self::$lastBlockType = 'newsletter';
    }

    /**
     * @param  iterable<array<string, mixed>>  $sections
     */
    public static function render(iterable $sections, string $locale, bool $isPreview = false): HtmlString
    {
        ['blocks' => $blocks, 'errors' => $errors] = BlockRegistry::parseSections($sections);
        if ($errors !== []) {
            logger()->warning('[blocks] skipped invalid sections', array_map(fn (array $e) => ['id' => $e['id'], 'type' => $e['type'], 'reason' => $e['reason']], $errors));
        }
        $ids = [];
        foreach ($blocks as $b) {
            $ids = BlockRegistry::collectAssetIds($b['content'], $ids);
        }
        $assets = Assets::byIds(array_keys($ids), $locale);

        $html = '';
        if ($isPreview) {
            foreach ($errors as $e) {
                $issues = '';
                if (! empty($e['issues'])) {
                    $issues = ' — '.implode('; ', array_map(fn (array $i) => implode('.', $i['path']).': '.$i['message'], $e['issues']));
                }
                $html .= '<div data-section-id="'.e($e['id']).'" role="alert" class="mx-auto my-4 max-w-5xl rounded-lg border border-red-400 bg-red-50 p-4 text-sm text-red-900">'
                    .'Section “'.e($e['type']).'” ('.e($e['id']).') is not shown: '.e($e['reason']).e($issues).'</div>';
            }
        }
        foreach ($blocks as $index => $block) {
            $context = ['locale' => $locale, 'assets' => $assets, 'isPreview' => $isPreview, 'index' => $index];
            $view = view('site.blocks.'.$block['type'], ['block' => $block, 'context' => $context, 'locale' => $locale])->render();
            // Preview only: a layout-neutral marker so the editor can scroll to / highlight a section.
            $html .= $isPreview ? '<div data-section-id="'.e($block['id']).'" class="contents">'.$view.'</div>' : $view;
            self::$lastBlockType = (string) $block['type'];
        }

        return new HtmlString($html);
    }

    /**
     * Render ONE already-parsed block directly (the reference renders e.g. <NewsletterBlock> on the
     * news page with an inline block object and no validation).
     *
     * @param  array{id: string, type: string, content: array<string, mixed>, settings: array<string, mixed>}  $block
     */
    public static function renderOne(array $block, string $locale, int $index = 0): HtmlString
    {
        $context = ['locale' => $locale, 'assets' => [], 'isPreview' => false, 'index' => $index];
        self::$lastBlockType = (string) $block['type'];

        return new HtmlString(view('site.blocks.'.$block['type'], ['block' => $block, 'context' => $context, 'locale' => $locale])->render());
    }

    /**
     * Whether the first visible section of a page is a hero (CMS pages then skip the page header).
     *
     * @param  iterable<array<string, mixed>>  $sections
     */
    public static function startsWithHero(iterable $sections): bool
    {
        foreach ($sections as $s) {
            if (($s['isVisible'] ?? true) !== false) {
                return ($s['type'] ?? null) === 'hero';
            }
        }

        return false;
    }
}
