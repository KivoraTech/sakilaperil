<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Cache\CacheTags;
use App\Content\ContentActionError;
use App\Content\ContentCommon;
use App\Content\ContentForms;
use App\Content\ContentSave;
use App\Http\Actions\ActionResult;
use App\Models\Event;
use App\Models\EventTranslation;
use App\Models\User;
use App\Queries\Admin\Events;
use App\RichText\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Events calendar (port of app/admin/(shell)/events/** and lib/actions/events.ts). Same permission model as projects. */
final class EventsController
{
    /** @return list<string> */
    private static function tagsFor(string $id): array
    {
        return [CacheTags::EVENTS, CacheTags::event($id), CacheTags::PAGES];
    }

    public function index(Request $request): Response
    {
        $params = ContentCommon::listParams($request->query());
        $whenRaw = $request->query('when');
        $whenRaw = is_array($whenRaw) ? ($whenRaw[0] ?? null) : $whenRaw;
        $when = $whenRaw === 'past' || $whenRaw === 'upcoming' ? $whenRaw : '';

        return Inertia::render('admin/events/index', [
            'params' => $params,
            'when' => $when,
            'list' => Events::list([...$params, 'when' => $when]),
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('admin/events/edit', [
            'id' => null,
            'status' => null,
            'initialValues' => null,
            'coverAsset' => null,
            'perms' => ['canEdit' => true, 'canPublish' => ContentCommon::can($user, 'content.publish'), 'canDelete' => false],
            'publicSlug' => null,
        ]);
    }

    public function edit(Request $request, string $id): Response|SymfonyResponse
    {
        /** @var User $user */
        $user = $request->user();
        $event = Events::forEdit($id);
        if ($event === null) {
            return AdminNotFound::response($request, 'event', '/admin/events', 'Back to events');
        }

        return Inertia::render('admin/events/edit', [
            'id' => $event['id'],
            'status' => $event['status'],
            'initialValues' => $event['values'],
            'coverAsset' => $event['coverAsset'],
            'perms' => [
                'canEdit' => $event['canEdit'],
                'canPublish' => ContentCommon::can($user, 'content.publish'),
                'canDelete' => ContentCommon::can($user, 'content.delete'),
            ],
            'publicSlug' => $event['slugs']['it'],
        ]);
    }

    /** saveEventAction(id, input, intent) */
    public function save(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $rawId = ContentCommon::body($request, 'id');
            $user = ContentCommon::requireCapability($rawId ? 'content.editOwn' : 'content.create');
            $intent = ContentForms::intent(ContentCommon::body($request, 'intent'));
            $eventId = $rawId ? ContentForms::id($rawId) : null;
            $v = ContentForms::event(ContentCommon::body($request, 'input'));
            $mayPublish = ContentCommon::can($user, 'content.publish');
            if ($intent === 'publish' && ! $mayPublish) {
                ContentCommon::fail('FORBIDDEN', 'Only editors can publish.');
            }

            $existing = $eventId ? Event::query()->find($eventId, ['id', 'createdById', 'status', 'publishedAt']) : null;
            if ($eventId && ! $existing) {
                ContentCommon::notFound('This event no longer exists.');
            }
            if ($existing) {
                if (! ContentCommon::canEditContent($user, $existing->createdById)) {
                    ContentCommon::fail('FORBIDDEN', 'You can only edit your own events.');
                }
                if (ContentCommon::val($existing->status) !== 'DRAFT' && ! $mayPublish) {
                    ContentCommon::fail('FORBIDDEN', 'This event is published or archived. Ask an editor to change it.');
                }
            }

            $tr = $v['translations'];
            $locales = ContentSave::locales($tr);
            $conflicts = ContentCommon::slugConflicts('event', $locales, $eventId);
            if ($conflicts !== []) {
                ContentCommon::slugConflict($conflicts);
            }
            ContentCommon::throwIfErrors(ContentCommon::assertMediaKinds([
                ['id' => $v['coverImageId'], 'kind' => 'IMAGE', 'field' => 'coverImageId'],
            ]));

            $existingStatus = $existing ? ContentCommon::val($existing->status) : null;
            $status = $intent === 'publish' ? 'PUBLISHED' : ($existingStatus ?? 'DRAFT');
            $bodies = ContentSave::bodies($locales);
            if ($status === 'PUBLISHED') {
                $missing = ContentCommon::imagesMissingAlt([$v['coverImageId'], ...ContentSave::bodyAssetIds($bodies)]);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }

            $data = [
                'status' => $status,
                'startsAt' => Carbon::parse($v['startsAt'])->utc(),
                'endsAt' => ContentCommon::dateOrNull($v['endsAt']),
                'allDay' => $v['allDay'],
                'timezone' => $v['timezone'],
                'address' => ContentCommon::blank($v['address']),
                'mapUrl' => ContentCommon::blank($v['mapUrl']),
                'onlineUrl' => ContentCommon::blank($v['onlineUrl']),
                'registrationUrl' => ContentCommon::blank($v['registrationUrl']),
                'coverImageId' => $v['coverImageId'],
                'isFeatured' => $v['isFeatured'],
                'publishedAt' => ContentSave::publishedAt($v['publishedAt'], $status, $existing?->publishedAt),
            ];

            $savedId = DB::transaction(function () use ($eventId, $data, $locales, $bodies, $tr, $user): string {
                $event = $eventId
                    ? tap(Event::query()->findOrFail($eventId))->update($data)
                    : Event::query()->create([...$data, 'createdById' => $user->id]);
                $rows = [];
                foreach ($locales as $i => $l) {
                    $rows[] = ['locale' => $l['locale'], 'fields' => [
                        'title' => $l['title'],
                        'slug' => $l['slug'],
                        'summary' => ContentCommon::blank($l['summary']),
                        'locationName' => ContentCommon::blank($l['locationName']),
                        'body' => $bodies[$i],
                        'seoTitle' => ContentCommon::blank($l['seoTitle']),
                        'seoDescription' => ContentCommon::blank($l['seoDescription']),
                    ]];
                }
                ContentSave::upsertTranslations(EventTranslation::class, 'eventId', $event->id, $rows, (bool) $tr['en']['enabled']);

                return (string) $event->id;
            });

            Audit::log($eventId ? AuditAction::CONTENT_UPDATED : AuditAction::CONTENT_CREATED, (string) $user->id, 'Event', $savedId, [
                'title' => $tr['it']['title'], 'status' => $status, 'locales' => array_column($locales, 'locale'),
            ]);
            if ($status === 'PUBLISHED' && $existingStatus !== 'PUBLISHED') {
                Audit::log(AuditAction::CONTENT_PUBLISHED, (string) $user->id, 'Event', $savedId);
            }
            ContentCommon::expireTags(...self::tagsFor($savedId));

            return ActionResult::ok(['id' => $savedId], ContentCommon::savedMessage($status, $data['publishedAt']));
        }, slugConflictOnUnique: true);
    }

    /** setEventStatusAction(id, status) */
    public function setStatus(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.publish');
            $eventId = ContentForms::id(ContentCommon::body($request, 'id'));
            $status = ContentForms::status(ContentCommon::body($request, 'status'));
            $event = Event::query()->with('translations:id,eventId,body')->find($eventId);
            if (! $event) {
                ContentCommon::notFound('This event no longer exists.');
            }
            if ($status === 'PUBLISHED') {
                $ids = [$event->coverImageId];
                foreach ($event->translations as $t) {
                    array_push($ids, ...RichText::collectAssetIds($t->body));
                }
                $missing = ContentCommon::imagesMissingAlt($ids);
                if ($missing !== []) {
                    ContentCommon::missingAlt($missing);
                }
            }
            $from = ContentCommon::val($event->status);
            $event->update(['status' => $status] + ($status === 'PUBLISHED' && ! $event->publishedAt ? ['publishedAt' => now()] : []));
            Audit::log(ContentCommon::statusAuditAction($status), (string) $user->id, 'Event', $eventId, ['from' => $from, 'to' => $status]);
            ContentCommon::expireTags(...self::tagsFor($eventId));

            return ActionResult::ok(['id' => $eventId], ContentCommon::statusMessage($status, $event->publishedAt));
        });
    }

    /** deleteEventAction(id) */
    public function delete(Request $request): JsonResponse
    {
        return ContentActionError::run(function () use ($request): JsonResponse {
            $user = ContentCommon::requireCapability('content.delete');
            $eventId = ContentForms::id(ContentCommon::body($request, 'id'));
            $event = Event::query()->with(['translations' => fn ($q) => $q->where('locale', 'it')])->find($eventId);
            if (! $event) {
                ContentCommon::notFound('This event no longer exists.');
            }
            $event->delete();
            Audit::log(AuditAction::CONTENT_DELETED, (string) $user->id, 'Event', $eventId, [
                'title' => $event->translations->first()?->title, 'status' => ContentCommon::val($event->status),
            ]);
            ContentCommon::expireTags(...self::tagsFor($eventId));

            return ActionResult::ok(['id' => $eventId], 'Event deleted');
        });
    }
}
