<?php

declare(strict_types=1);

namespace App\View;

use App\I18n\Locales;

/** Navigation helpers matching next-intl's usePathname() and header-nav.tsx isActive(). */
final class Nav
{
    /** Current path WITHOUT the locale prefix and query ("/it/projects?x=1" → "/projects", "/it" → "/"). */
    public static function pathname(): string
    {
        $path = '/'.ltrim(request()->path(), '/');
        foreach (Locales::ALL as $l) {
            if ($path === '/'.$l) {
                return '/';
            }
            if (str_starts_with($path, '/'.$l.'/')) {
                return substr($path, strlen($l) + 1);
            }
        }

        return $path;
    }

    public static function isActive(string $pathname, string $href): bool
    {
        if ($href === '/') {
            return $pathname === '/';
        }
        $path = explode('#', $href)[0];

        return $pathname === $path || str_starts_with($pathname, $path.'/');
    }
}
