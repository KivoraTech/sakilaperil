<?php

declare(strict_types=1);

namespace App\Pages;

use App\Cache\CacheTags;
use App\Cache\TagCache;

/**
 * Cache expiry for the builder (reference lib/pages/revalidate.ts). Only called when the PUBLIC
 * output changes; drafts never expire anything.
 */
final class PageCache
{
    /** A page was published/unpublished/deleted or its live slug/noindex changed. */
    public static function expirePage(?string $pageId = null): void
    {
        // `navigation` depends on pages too (menu links to unpublished pages are dropped).
        $tags = [CacheTags::PAGES, CacheTags::NAVIGATION];
        if ($pageId !== null) {
            $tags[] = CacheTags::page($pageId);
        }
        TagCache::invalidate(...$tags);
    }

    public static function expireNavigation(): void
    {
        TagCache::invalidate(CacheTags::NAVIGATION);
    }

    /** Settings feed the layout (header/footer), impact numbers, donate widget, SEO defaults. */
    public static function expireSettings(): void
    {
        TagCache::invalidate(CacheTags::SETTINGS);
    }
}
