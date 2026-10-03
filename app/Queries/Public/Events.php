<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\ContentStatus;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Port of lib/queries/public/events.ts (events are public when PUBLISHED; no publishedAt gate).
 * EventSummary: {id, slug, title, summary, locationName, startsAt, endsAt, allDay, timezone, coverImage,
 * isFallback}; EventDetail adds body, address, mapUrl, onlineUrl, registrationUrl, seoTitle,
 * seoDescription, alternates.
 */
final class Events
{
    /**
     * @param  array{when?: 'upcoming'|'past'|'all', limit?: int}  $opts
     * @return list<array<string, mixed>>
     */
    public static function list(string $locale, array $opts = []): array
    {
        // Cache key includes the hour so "upcoming" rolls over without a publish.
        $key = 'events:'.$locale.':'.md5((string) json_encode($opts)).':'.Carbon::now('UTC')->format('Y-m-d\TH');

        return TagCache::remember([CacheTags::EVENTS], $key, function () use ($locale, $opts): array {
            $now = Carbon::now('UTC');
            $when = $opts['when'] ?? 'upcoming';
            $q = Event::query()->where('status', ContentStatus::PUBLISHED->value);
            if ($when === 'upcoming') {
                $q->where(fn (Builder $w) => $w->where('endsAt', '>=', $now)->orWhere(fn (Builder $x) => $x->whereNull('endsAt')->where('startsAt', '>=', $now)));
            } elseif ($when === 'past') {
                $q->where(fn (Builder $w) => $w->where('endsAt', '<', $now)->orWhere(fn (Builder $x) => $x->whereNull('endsAt')->where('startsAt', '<', $now)));
            }
            $rows = $q->with(['translations' => fn ($t) => $t->whereIn('locale', Shared::translationLocales($locale)), 'coverImage'])
                ->orderBy('startsAt', $when === 'past' ? 'desc' : 'asc')
                ->limit(min($opts['limit'] ?? 50, 100))
                ->get();
            $out = [];
            foreach ($rows as $r) {
                $s = self::summary($r, $locale);
                if ($s !== null) {
                    $out[] = $s;
                }
            }

            return $out;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $locale, string $slug): ?array
    {
        return TagCache::remember([CacheTags::EVENTS], "event:{$locale}:{$slug}", function () use ($locale, $slug): ?array {
            $r = Event::query()
                ->where('status', ContentStatus::PUBLISHED->value)
                ->whereHas('translations', fn (Builder $t) => $t->where('slug', $slug)->whereIn('locale', Shared::translationLocales($locale)))
                ->with(['translations', 'coverImage'])
                ->first();
            if ($r === null) {
                return null;
            }
            $s = self::summary($r, $locale);
            if ($s === null) {
                return null;
            }
            [$t] = Shared::pick($r->translations, $locale);
            $alternates = [];
            foreach ($r->translations as $tr) {
                $alternates[Shared::localeOf($tr)] = Routes::event((string) $tr->slug);
            }

            return [
                ...$s,
                'body' => is_array($t->body) ? $t->body : null,
                'address' => $r->address,
                'mapUrl' => $r->mapUrl,
                'onlineUrl' => $r->onlineUrl,
                'registrationUrl' => $r->registrationUrl,
                'seoTitle' => $t->seoTitle,
                'seoDescription' => $t->seoDescription,
                'alternates' => $alternates,
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function summary(Event $r, string $locale): ?array
    {
        $picked = Shared::pick($r->translations, $locale);
        if ($picked === null) {
            return null;
        }
        [$t, $fallback] = $picked;

        return [
            'id' => (string) $r->id,
            'slug' => (string) $t->slug,
            'title' => (string) $t->title,
            'summary' => $t->summary,
            'locationName' => $t->locationName,
            'startsAt' => Shared::iso($r->startsAt),
            'endsAt' => Shared::iso($r->endsAt),
            'allDay' => (bool) $r->allDay,
            'timezone' => (string) $r->timezone,
            'coverImage' => Assets::toDto($r->coverImage, $locale),
            'isFallback' => $fallback,
        ];
    }
}
