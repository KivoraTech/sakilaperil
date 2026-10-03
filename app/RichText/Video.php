<?php

declare(strict_types=1);

namespace App\RichText;

use InvalidArgumentException;

/**
 * YouTube / Vimeo helpers (port of sakila/lib/richtext/video.ts). Videos are never hosted
 * (ADR-004): only { provider, id } validated by regex is stored and the embed URL is built here,
 * so no user-supplied URL or HTML ever reaches an iframe.
 */
final class Video
{
    /** @var array<string, string> */
    public const ID_PATTERNS = [
        'youtube' => '/^[A-Za-z0-9_-]{11}$/D',
        'vimeo' => '/^\d{1,12}$/D',
    ];

    private const YOUTUBE_HOSTS = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com',
    ];

    private const VIMEO_HOSTS = ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];

    public static function isValidId(mixed $provider, mixed $id): bool
    {
        return ($provider === 'youtube' || $provider === 'vimeo')
            && is_string($id)
            && preg_match(self::ID_PATTERNS[$provider], $id) === 1;
    }

    /**
     * Parse a pasted YouTube/Vimeo URL (watch, short, embed, shorts, player).
     *
     * @return array{provider: string, id: string}|null
     */
    public static function parseUrl(string $input): ?array
    {
        $parts = parse_url(trim($input));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            return null;
        }
        $host = strtolower($parts['host']);
        $segments = array_values(array_filter(explode('/', $parts['path'] ?? ''), fn (string $s): bool => $s !== ''));
        parse_str($parts['query'] ?? '', $query);

        $provider = null;
        $id = null;
        if ($host === 'youtu.be') {
            $provider = 'youtube';
            $id = $segments[0] ?? null;
        } elseif (in_array($host, self::YOUTUBE_HOSTS, true)) {
            $provider = 'youtube';
            if (($segments[0] ?? '') === 'watch') {
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (in_array($segments[0] ?? '', ['embed', 'shorts', 'live', 'v'], true)) {
                $id = $segments[1] ?? null;
            }
        } elseif (in_array($host, self::VIMEO_HOSTS, true)) {
            $provider = 'vimeo';
            if ($host === 'player.vimeo.com' && ($segments[0] ?? '') === 'video') {
                $id = $segments[1] ?? null;
            } else {
                foreach ($segments as $s) {
                    if (preg_match('/^\d+$/D', $s) === 1) {
                        $id = $s;
                        break;
                    }
                }
            }
        }
        if ($provider === null || $id === null || ! self::isValidId($provider, $id)) {
            return null;
        }

        return ['provider' => $provider, 'id' => $id];
    }

    /** Privacy-friendly embed URL (youtube-nocookie / Vimeo do-not-track). */
    public static function embedUrl(string $provider, string $id): string
    {
        if (! self::isValidId($provider, $id)) {
            throw new InvalidArgumentException('Invalid video id');
        }

        return $provider === 'youtube'
            ? "https://www.youtube-nocookie.com/embed/{$id}?rel=0"
            : "https://player.vimeo.com/video/{$id}?dnt=1";
    }

    /** Canonical watch URL (for "open on YouTube/Vimeo" links). */
    public static function watchUrl(string $provider, string $id): string
    {
        return $provider === 'youtube' ? "https://www.youtube.com/watch?v={$id}" : "https://vimeo.com/{$id}";
    }

    /** Static thumbnail (YouTube only; Vimeo needs an API call, so callers show a placeholder). */
    public static function thumbnailUrl(string $provider, string $id): ?string
    {
        return $provider === 'youtube' && self::isValidId($provider, $id)
            ? "https://i.ytimg.com/vi/{$id}/hqdefault.jpg"
            : null;
    }
}
