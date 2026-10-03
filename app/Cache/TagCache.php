<?php

declare(strict_types=1);

namespace App\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Tag-style invalidation on ANY cache store (the file store on shared hosting has no tags):
 * every tag has a version stored in the cache; an entry's key includes the versions of its tags,
 * so bumping a tag version makes all dependent entries unreachable at once (they expire later).
 * Works across PHP processes because the versions live in the shared store.
 *
 * Isolation between databases is done by the STORE, not by the key names: config/cache.php gives
 * the file store one folder per database and puts the same scope in the key prefix of the other
 * stores, so two databases on one checkout never read each other's entries.
 *
 *   TagCache::remember([CacheTags::PROJECTS], "projects:list:{$locale}", fn () => …);
 *   TagCache::invalidate(CacheTags::PROJECTS, CacheTags::project($id));   // after a mutation
 */
final class TagCache
{
    /**
     * @template T
     *
     * @param  list<string>  $tags
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(array $tags, string $key, Closure $callback, int $ttl = CacheTags::PUBLIC_REVALIDATE_SECONDS): mixed
    {
        if (! config('sakila.public_cache', true)) {
            return $callback();
        }
        $versions = array_map(fn (string $tag): string => self::version($tag), $tags);
        $fullKey = 'pub:'.$key.':'.substr(sha1(implode('|', $tags).'#'.implode('|', $versions)), 0, 16);

        return Cache::remember($fullKey, $ttl, $callback);
    }

    public static function invalidate(string ...$tags): void
    {
        foreach (array_unique($tags) as $tag) {
            Cache::forever(self::versionKey($tag), bin2hex(random_bytes(6)));
        }
    }

    /**
     * A missing version always becomes a NEW random one, never a fixed value: the file store
     * truncates a file before rewriting it, so a concurrent reader can briefly see "missing".
     * Re-creating a constant like '0' there would make entries cached under '0' long ago
     * reachable again (a just-published item vanishing from lists). A fresh random version can
     * only cause an extra cache miss.
     */
    private static function version(string $tag): string
    {
        $key = self::versionKey($tag);
        $v = Cache::get($key);
        if (is_string($v) && $v !== '') {
            return $v;
        }
        $fresh = bin2hex(random_bytes(6));
        Cache::add($key, $fresh);
        $v = Cache::get($key);

        return is_string($v) && $v !== '' ? $v : $fresh;
    }

    private static function versionKey(string $tag): string
    {
        return 'tagv:'.$tag;
    }
}
