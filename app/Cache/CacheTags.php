<?php

declare(strict_types=1);

namespace App\Cache;

/** Cache tags for public content — 1:1 with sakila/lib/cache/tags.ts. */
final class CacheTags
{
    public const SETTINGS = 'settings';

    public const NAVIGATION = 'navigation';

    public const PAGES = 'pages';

    public const PROJECTS = 'projects';

    public const NEWS = 'news';

    public const EVENTS = 'events';

    public const ALBUMS = 'albums';

    public const REPORTS = 'reports';

    public const MEDIA = 'media';

    /** Default time-based safety net for cached public reads (seconds). */
    public const PUBLIC_REVALIDATE_SECONDS = 3600;

    public static function page(string $id): string
    {
        return "page:{$id}";
    }

    public static function project(string $id): string
    {
        return "project:{$id}";
    }

    public static function newsPost(string $id): string
    {
        return "news:{$id}";
    }

    public static function event(string $id): string
    {
        return "event:{$id}";
    }

    public static function album(string $id): string
    {
        return "album:{$id}";
    }
}
