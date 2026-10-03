<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\ContentStatus;
use App\Enums\NavigationLinkType;
use App\I18n\Locales;
use App\I18n\Routes;
use App\Models\Navigation as NavigationModel;
use App\Models\NavigationItem;
use App\Models\Page;
use App\View\Ui;

/**
 * Port of lib/queries/public/navigation.ts. Resolved, visible links for a menu
 * ("header" | "footer-explore" | "footer-help" | "footer-legal"); links to unpublished pages are
 * dropped so drafts never leak through menus.
 *
 * NavLink: {id, label, href (locale-less path or https URL), external, openInNewTab, children}
 */
final class Navigation
{
    /**
     * @return list<array{id: string, label: string, href: string, external: bool, openInNewTab: bool, children: list<array<string, mixed>>}>
     */
    public static function get(string $key, string $locale): array
    {
        return TagCache::remember([CacheTags::NAVIGATION, CacheTags::PAGES], "navigation:{$key}:{$locale}", fn () => self::load($key, $locale));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function load(string $key, string $locale): array
    {
        $nav = NavigationModel::query()->where('key', $key)->first();
        if ($nav === null) {
            return [];
        }
        $items = NavigationItem::query()
            ->where('navigationId', $nav->id)
            ->where('isVisible', true)
            ->orderBy('position')
            ->with(['page' => fn ($q) => $q->select(['id', 'status'])->with(['translations' => fn ($t) => $t->whereNotNull('publishedRevisionId')->select(['id', 'pageId', 'locale', 'slug'])])])
            ->get();

        $keyed = Page::query()
            ->whereNotNull('key')
            ->where('status', ContentStatus::PUBLISHED->value)
            ->with(['translations' => fn ($t) => $t->whereNotNull('publishedRevisionId')->select(['id', 'pageId', 'locale', 'slug'])])
            ->get(['id', 'key']);
        $slugForKey = function (string $k) use ($keyed, $locale): ?string {
            $p = $keyed->firstWhere('key', $k);
            if ($p === null) {
                return null;
            }

            return self::slugFor($p->translations, $locale);
        };

        $resolve = function (NavigationItem $item) use ($locale, $slugForKey): ?string {
            switch ($item->linkType) {
                case NavigationLinkType::EXTERNAL:
                case NavigationLinkType::INTERNAL:
                    return $item->href !== null && preg_match(Ui::SAFE_HREF, $item->href) === 1 ? $item->href : null;
                case NavigationLinkType::PAGE:
                    $page = $item->page;
                    if ($page === null || $page->status !== ContentStatus::PUBLISHED) {
                        return null;
                    }
                    $slug = self::slugFor($page->translations, $locale);

                    return $slug !== null ? Routes::page($slug) : null;
                case NavigationLinkType::ROUTE:
                    $rk = (string) ($item->routeKey ?? '');
                    if (isset(Routes::COLLECTION_PATHS[$rk])) {
                        return Routes::COLLECTION_PATHS[$rk];
                    }
                    if ($rk === 'donate') {
                        return '/donate';
                    }
                    $s = $slugForKey($rk);

                    return $s !== null ? Routes::page($s) : null;
            }
        };

        $build = function (?string $parentId) use (&$build, $items, $resolve, $locale): array {
            $out = [];
            foreach ($items as $i) {
                if ($i->parentId !== $parentId) {
                    continue;
                }
                $href = $resolve($i);
                if ($href === null) {
                    continue;
                }
                $out[] = [
                    'id' => (string) $i->id,
                    'label' => Locales::pick($i->label, $locale),
                    'href' => $href,
                    'external' => $i->linkType === NavigationLinkType::EXTERNAL,
                    'openInNewTab' => (bool) $i->openInNewTab,
                    'children' => $build((string) $i->id),
                ];
            }

            return $out;
        };

        return $build(null);
    }

    /**
     * Slug of a page for a menu in `$locale`, from its PUBLISHED translations: this locale's
     * version, else the Italian one (which /{locale}/<slug> redirects to). A page that is not
     * published in this locale and has no Italian version gets no link (it would be a 404).
     *
     * @param  iterable<object>  $translations
     */
    private static function slugFor(iterable $translations, string $locale): ?string
    {
        $default = null;
        foreach ($translations as $t) {
            $l = Shared::localeOf($t);
            if ($l === $locale) {
                return (string) $t->slug;
            }
            if ($l === Locales::DEFAULT) {
                $default = (string) $t->slug;
            }
        }

        return $default;
    }
}
