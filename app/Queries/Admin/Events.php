<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\I18n\Routes;
use App\Media\Assets;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Admin reads for events (port of sakila/lib/queries/admin/events.ts). */
final class Events
{
    /**
     * @param  array{q: string, status: string, page: int, when?: string}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $user = ContentCommon::requireCapability('content.create');
        $when = $p['when'] ?? '';
        $now = Carbon::now();
        $query = Event::query()
            ->when($p['status'] !== '', fn (Builder $b) => $b->where('status', $p['status']))
            ->when($p['q'] !== '', fn (Builder $b) => SharedContent::titleSearch($b, $p['q']))
            ->when($when === 'upcoming', fn (Builder $b) => $b->where('startsAt', '>=', $now))
            ->when($when === 'past', fn (Builder $b) => $b->where('startsAt', '<', $now));
        $total = (clone $query)->count();
        $rows = $query->with('translations:id,eventId,locale,title,slug,locationName')
            ->orderBy('startsAt', $when === 'past' ? 'desc' : 'asc')
            ->limit(ContentCommon::PAGE_SIZE)->offset(($p['page'] - 1) * ContentCommon::PAGE_SIZE)
            ->get();
        $mayPublish = ContentCommon::can($user, 'content.publish');

        return [
            'items' => $rows->map(function (Event $r) use ($user, $mayPublish): array {
                $status = ContentCommon::val($r->status);

                return [
                    'id' => $r->id,
                    ...ContentCommon::itTitle($r->translations),
                    'status' => $status,
                    'scheduled' => ContentCommon::isScheduled($status, $r->publishedAt),
                    'locales' => $r->translations->map(fn ($t) => ContentCommon::val($t->locale))->values()->all(),
                    'updatedAt' => ContentCommon::iso($r->updatedAt),
                    // The table's date column shows the event start for events.
                    'publishedAt' => ContentCommon::iso($r->startsAt),
                    'meta' => ContentCommon::byLocale($r->translations, 'it')->locationName ?? $r->address,
                    'canEdit' => ContentCommon::canEditContent($user, $r->createdById) && ($status === 'DRAFT' || $mayPublish),
                ];
            })->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => ContentCommon::pageCount($total),
        ];
    }

    /** @return array<string, mixed>|null EventForEdit of the reference */
    public static function forEdit(string $id): ?array
    {
        $user = ContentCommon::requireCapability('content.create');
        $r = Event::query()->with('translations')->find($id);
        if (! $r) {
            return null;
        }
        $it = ContentCommon::byLocale($r->translations, 'it');
        $en = ContentCommon::byLocale($r->translations, 'en');
        $tr = fn ($t): array => [
            'title' => (string) ($t->title ?? ''),
            'slug' => (string) ($t->slug ?? ''),
            'summary' => (string) ($t->summary ?? ''),
            'locationName' => (string) ($t->locationName ?? ''),
            'body' => ContentCommon::docOrEmpty($t?->body),
            'seoTitle' => (string) ($t->seoTitle ?? ''),
            'seoDescription' => (string) ($t->seoDescription ?? ''),
        ];
        $media = Media::dtosByIds([$r->coverImageId]);
        $status = ContentCommon::val($r->status);

        return [
            'id' => $r->id,
            'status' => $status,
            'values' => [
                'startsAt' => ContentCommon::iso($r->startsAt),
                'endsAt' => ContentCommon::isoOrEmpty($r->endsAt),
                'allDay' => (bool) $r->allDay,
                'timezone' => in_array($r->timezone, ContentForms::EVENT_TIMEZONES, true) ? $r->timezone : 'Europe/Rome',
                'address' => (string) ($r->address ?? ''),
                'mapUrl' => (string) ($r->mapUrl ?? ''),
                'onlineUrl' => (string) ($r->onlineUrl ?? ''),
                'registrationUrl' => (string) ($r->registrationUrl ?? ''),
                'coverImageId' => $r->coverImageId,
                'isFeatured' => (bool) $r->isFeatured,
                'publishedAt' => ContentCommon::isoOrEmpty($r->publishedAt),
                'translations' => ['it' => $tr($it), 'en' => [...$tr($en), 'enabled' => $en !== null]],
            ],
            'coverAsset' => $r->coverImageId ? ($media[$r->coverImageId] ?? null) : null,
            'slugs' => ['it' => $it?->slug, 'en' => $en?->slug],
            'canEdit' => ContentCommon::canEditContent($user, $r->createdById) && ($status === 'DRAFT' || ContentCommon::can($user, 'content.publish')),
        ];
    }

    /** @return array<string, mixed>|null EventDetail of the public site + status */
    public static function draftForPreview(string $id, string $locale): ?array
    {
        ContentCommon::requireCapability('content.create');
        $r = Event::query()->with(['translations', 'coverImage'])->find($id);
        if (! $r) {
            return null;
        }
        $t = SharedContent::pickTranslation($r->translations, $locale);
        if ($t === null) {
            return null;
        }
        $row = $t['row'];
        $alternates = [];
        foreach ($r->translations as $tr) {
            $alternates[ContentCommon::val($tr->locale)] = Routes::event((string) $tr->slug);
        }

        return [
            'status' => ContentCommon::val($r->status),
            'id' => $r->id,
            'slug' => (string) $row->slug,
            'title' => (string) $row->title,
            'summary' => $row->summary,
            'locationName' => $row->locationName,
            'startsAt' => ContentCommon::iso($r->startsAt),
            'endsAt' => ContentCommon::iso($r->endsAt),
            'allDay' => (bool) $r->allDay,
            'timezone' => $r->timezone,
            'coverImage' => Assets::toDto($r->coverImage, $locale),
            'isFallback' => $t['isFallback'],
            'body' => is_array($row->body) ? $row->body : null,
            'address' => $r->address,
            'mapUrl' => $r->mapUrl,
            'onlineUrl' => $r->onlineUrl,
            'registrationUrl' => $r->registrationUrl,
            'seoTitle' => $row->seoTitle,
            'seoDescription' => $row->seoDescription,
            'alternates' => $alternates,
        ];
    }
}
