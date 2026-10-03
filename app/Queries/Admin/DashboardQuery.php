<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Auth\Permissions;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\Donation;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\MembershipApplication;
use App\Models\NewsletterSubscriber;
use App\Models\NewsPost;
use App\Models\Page;
use App\Models\PartnerInquiry;
use App\Models\Project;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Pages\Json;
use App\Settings\SettingsTodo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard data — port of the reference's lib/queries/admin/dashboard.ts. Real numbers only;
 * sections the user may not see (personal data, donations, audit) are not even queried.
 */
final class DashboardQuery
{
    /** Plain-English verbs for the activity feed ("published a page"). */
    private const ACTION_VERBS = [
        'USER_LOGIN' => 'signed in',
        'USER_LOGIN_FAILED' => 'failed to sign in',
        'USER_LOGOUT' => 'signed out',
        'USER_CREATED' => 'added',
        'USER_UPDATED' => 'updated',
        'USER_ROLE_CHANGED' => 'changed the role of',
        'USER_DEACTIVATED' => 'deactivated',
        'USER_PASSWORD_RESET' => 'reset the password of',
        'PAGE_CREATED' => 'created',
        'PAGE_UPDATED' => 'edited',
        'PAGE_PUBLISHED' => 'published',
        'PAGE_UNPUBLISHED' => 'unpublished',
        'PAGE_DELETED' => 'deleted',
        'PAGE_REVERTED' => 'restored an older version of',
        'CONTENT_CREATED' => 'created',
        'CONTENT_UPDATED' => 'edited',
        'CONTENT_PUBLISHED' => 'published',
        'CONTENT_UNPUBLISHED' => 'unpublished',
        'CONTENT_ARCHIVED' => 'archived',
        'CONTENT_DELETED' => 'deleted',
        'MEDIA_UPLOADED' => 'uploaded',
        'MEDIA_UPDATED' => 'edited',
        'MEDIA_DELETED' => 'deleted',
        'NAVIGATION_UPDATED' => 'updated',
        'SETTINGS_UPDATED' => 'updated',
        'SUBMISSION_UPDATED' => 'updated',
        'SUBMISSION_DELETED' => 'deleted',
        'SUBMISSIONS_EXPORTED' => 'exported',
        'DONATION_UPDATED' => 'updated',
        'DONATIONS_EXPORTED' => 'exported',
    ];

    private const ENTITY_NOUNS = [
        'Page' => 'a page',
        'NewsPost' => 'a news story',
        'Project' => 'a project',
        'Event' => 'an event',
        'GalleryAlbum' => 'a gallery album',
        'Report' => 'a report',
        'MediaAsset' => 'a photo or file',
        'Navigation' => 'the menus',
        'NewsCategory' => 'a news category',
        'SiteSettings' => 'the settings',
        'User' => 'a user',
        'Donation' => 'a donation',
    ];

    /** Things set up by the seed count as "demo"; real work happens after this grace period. */
    private const SETUP_GRACE_SECONDS = 10 * 60;

    /** "published a news story", "signed in", "exported submissions". */
    public static function describeActivity(string $action, ?string $entityType): string
    {
        $verb = self::ACTION_VERBS[$action] ?? str_replace('_', ' ', strtolower($action));
        if ($action === 'SUBMISSIONS_EXPORTED') {
            return 'exported form submissions';
        }
        if ($action === 'DONATIONS_EXPORTED') {
            return 'exported donations';
        }
        if (str_starts_with($action, 'USER_LOG')) {
            return $verb;
        }
        $noun = $entityType !== null && $entityType !== '' ? (self::ENTITY_NOUNS[$entityType] ?? strtolower($entityType)) : '';

        return $noun !== '' ? "{$verb} {$noun}" : $verb;
    }

    /**
     * "Getting started" steps, derived from real data only. Anything created more than 10 minutes
     * after the site settings row counts as the organisation's own work.
     *
     * @param  array{setupAt: ?CarbonInterface, canSettings: bool, canContent: bool, canPages: bool, todoCount: ?int, demoContent: bool, lastNewsCreatedAt: ?CarbonInterface, lastMediaCreatedAt: ?CarbonInterface, homePublishedAt: ?CarbonInterface, homeId: ?string}  $input
     * @return list<array{id: string, label: string, hint: string, href: string, done: bool}>
     */
    public static function buildChecklist(array $input): array
    {
        $after = fn (?CarbonInterface $d): bool => $d !== null
            && ($input['setupAt'] === null || $d->getTimestampMs() > $input['setupAt']->getTimestampMs() + self::SETUP_GRACE_SECONDS * 1000);
        $items = [];
        if ($input['canSettings']) {
            $items[] = [
                'id' => 'details',
                'label' => 'Fill in your organisation details',
                'hint' => 'Address, bank details (IBAN) and contacts. Unfinished details stay hidden on the website.',
                'href' => '/admin/settings',
                'done' => $input['todoCount'] === 0,
            ];
        }
        if ($input['canPages'] && $input['homeId'] !== null) {
            $items[] = [
                'id' => 'home',
                'label' => 'Make the home page yours',
                'hint' => 'Change the big photo, the title and the numbers, then click Publish.',
                'href' => "/admin/pages/{$input['homeId']}",
                'done' => $after($input['homePublishedAt']),
            ];
        }
        if ($input['canContent']) {
            $items[] = [
                'id' => 'photos',
                'label' => 'Upload your own photos',
                'hint' => 'Photos from Nepal and your events make the website feel real.',
                'href' => '/admin/media',
                'done' => $after($input['lastMediaCreatedAt']),
            ];
            $items[] = [
                'id' => 'news',
                'label' => 'Write your first news story',
                'hint' => 'A short update from a project is perfect.',
                'href' => '/admin/news/new',
                'done' => $after($input['lastNewsCreatedAt']),
            ];
        }
        if ($input['canSettings']) {
            $items[] = [
                'id' => 'demo',
                'label' => 'Turn off the “demo content” notice',
                'hint' => 'Do this once the example texts and photos are replaced (Settings → Organisation).',
                'href' => '/admin/settings',
                'done' => ! $input['demoContent'],
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    public static function data(User $user): array
    {
        PagesQuery::authorize($user, 'dashboard.view');
        $role = $user->role;
        $canSubmissions = Permissions::can($role, 'submissions.read');
        $canDonations = Permissions::can($role, 'donations.read');
        $canAudit = Permissions::can($role, 'audit.read');
        $canSettings = Permissions::can($role, 'settings.manage');
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        $title = fn (Collection $ts): string => (string) (data_get($ts->first(fn ($t) => $t->locale->value === 'it') ?? $ts->first(), 'title') ?? '(untitled)');

        $pages = [
            'published' => Page::query()->where('status', 'PUBLISHED')->count(),
            'draft' => Page::query()->where('status', 'DRAFT')->count(),
            'withChanges' => Page::query()->whereHas('translations', fn (Builder $q) => $q->where('hasDraftChanges', true)->whereNotNull('publishedRevisionId'))->count(),
        ];
        $news = [
            'published' => NewsPost::query()->where('status', 'PUBLISHED')->count(),
            'drafts' => NewsPost::query()->where('status', 'DRAFT')->count(),
            'recent' => NewsPost::query()->orderByDesc('updatedAt')->take(5)->with('translations:id,postId,locale,title')->get()
                ->map(fn (NewsPost $n) => [
                    'id' => $n->id,
                    'title' => $title($n->translations),
                    'status' => $n->status->value,
                    'updatedAt' => Json::iso($n->updatedAt),
                ])->values()->all(),
        ];
        $events = Event::query()->where('startsAt', '>=', $now)->orderBy('startsAt')->take(3)->with('translations:id,eventId,locale,title')->get()
            ->map(fn (Event $e) => ['id' => $e->id, 'title' => $title($e->translations), 'status' => $e->status->value, 'startsAt' => Json::iso($e->startsAt)])
            ->values()->all();

        $submissions = null;
        if ($canSubmissions) {
            $contact = ContactSubmission::query()->where('status', 'NEW')->count();
            $volunteer = VolunteerApplication::query()->where('status', 'NEW')->count();
            $membership = MembershipApplication::query()->where('status', 'NEW')->count();
            $partner = PartnerInquiry::query()->where('status', 'NEW')->count();
            $submissions = [
                'contact' => $contact,
                'volunteer' => $volunteer,
                'membership' => $membership,
                'partner' => $partner,
                'newsletter' => NewsletterSubscriber::query()->where('status', 'SUBSCRIBED')->count(),
                'total' => $contact + $volunteer + $membership + $partner,
            ];
        }

        $donations = null;
        if ($canDonations) {
            $donations = [
                'recent' => Donation::query()->where('status', 'SUCCEEDED')->orderByDesc('completedAt')->take(5)
                    ->get(['id', 'reference', 'provider', 'amount', 'currency', 'donorName', 'isAnonymous', 'isTestMode', 'completedAt', 'createdAt'])
                    ->map(fn (Donation $d) => [
                        'id' => $d->id,
                        'reference' => $d->reference,
                        'provider' => $d->provider->value,
                        'amount' => $d->amount,
                        'currency' => $d->currency,
                        'donorName' => $d->donorName,
                        'isAnonymous' => (bool) $d->isAnonymous,
                        'isTestMode' => (bool) $d->isTestMode,
                        'completedAt' => Json::iso($d->completedAt),
                        'createdAt' => Json::iso($d->createdAt),
                    ])->values()->all(),
                'month' => Donation::query()
                    ->where('status', 'SUCCEEDED')->where('isTestMode', false)->where('completedAt', '>=', $monthStart)
                    ->groupBy('currency')
                    ->get(['currency', DB::raw('COALESCE(SUM(amount), 0) as total'), DB::raw('COUNT(*) as n')])
                    ->map(fn ($m) => ['currency' => $m->currency, 'amount' => (int) $m->getAttribute('total'), 'count' => (int) $m->getAttribute('n')])
                    ->values()->all(),
                'pendingBank' => Donation::query()->where('status', 'PENDING')->where('provider', 'BANK_TRANSFER')->count(),
            ];
        }

        $activity = null;
        if ($canAudit) {
            $activity = AuditLog::query()->where('action', '!=', 'USER_LOGIN_FAILED')->orderByDesc('createdAt')->take(8)->with('user:id,name')->get()
                ->map(fn (AuditLog $a) => [
                    'id' => $a->id,
                    'action' => $a->action,
                    'entityType' => $a->entityType,
                    'entityId' => $a->entityId,
                    'description' => self::describeActivity($a->action, $a->entityType),
                    'user' => $a->user?->name,
                    'createdAt' => Json::iso($a->createdAt),
                ])->values()->all();
        }

        $todoCount = null;
        $settingsRow = DB::table('site_settings')->where('id', 'default')->first();
        if ($canSettings && $settingsRow !== null) {
            $n = 0;
            $walk = function (mixed $v) use (&$walk, &$n): void {
                if (SettingsTodo::isTodo($v)) {
                    $n++;
                } elseif (is_array($v)) {
                    foreach ($v as $x) {
                        $walk($x);
                    }
                }
            };
            foreach (['organization', 'contact', 'bankDetails', 'nepalPayments'] as $col) {
                $walk(is_string($settingsRow->{$col} ?? null) ? json_decode($settingsRow->{$col}, true) : null);
            }
            $todoCount = $n;
        }

        // "Your drafts": what this user can continue working on (authors: only their own items).
        $canContent = Permissions::can($role, 'content.create');
        $canPages = Permissions::can($role, 'pages.view');
        $editAll = Permissions::can($role, 'content.editAll');
        $drafts = [];
        if ($canPages) {
            foreach (Page::query()->whereHas('translations', fn (Builder $q) => $q->where('hasDraftChanges', true))
                ->orderByDesc('updatedAt')->take(5)->with('translations:id,pageId,locale,title')->get() as $d) {
                $drafts[] = ['key' => "page:{$d->id}", 'kind' => 'Page', 'title' => $title($d->translations), 'href' => "/admin/pages/{$d->id}", 'updatedAt' => Json::iso($d->updatedAt), 'isLive' => $d->status->value === 'PUBLISHED'];
            }
        }
        if ($canContent) {
            foreach (NewsPost::query()->where('status', 'DRAFT')->when(! $editAll, fn ($q) => $q->where('authorId', $user->id))
                ->orderByDesc('updatedAt')->take(5)->with('translations:id,postId,locale,title')->get() as $d) {
                $drafts[] = ['key' => "news:{$d->id}", 'kind' => 'News story', 'title' => $title($d->translations), 'href' => "/admin/news/{$d->id}", 'updatedAt' => Json::iso($d->updatedAt), 'isLive' => false];
            }
            foreach (Project::query()->where('status', 'DRAFT')->when(! $editAll, fn ($q) => $q->where('createdById', $user->id))
                ->orderByDesc('updatedAt')->take(5)->with('translations:id,projectId,locale,title')->get() as $d) {
                $drafts[] = ['key' => "project:{$d->id}", 'kind' => 'Project', 'title' => $title($d->translations), 'href' => "/admin/projects/{$d->id}", 'updatedAt' => Json::iso($d->updatedAt), 'isLive' => false];
            }
            foreach (Event::query()->where('status', 'DRAFT')->when(! $editAll, fn ($q) => $q->where('createdById', $user->id))
                ->orderByDesc('updatedAt')->take(5)->with('translations:id,eventId,locale,title')->get() as $d) {
                $drafts[] = ['key' => "event:{$d->id}", 'kind' => 'Event', 'title' => $title($d->translations), 'href' => "/admin/events/{$d->id}", 'updatedAt' => Json::iso($d->updatedAt), 'isLive' => false];
            }
        }
        usort($drafts, fn ($a, $b) => strcmp((string) $b['updatedAt'], (string) $a['updatedAt']));
        $drafts = array_slice($drafts, 0, 5);

        $home = Page::query()->where('key', 'home')->with('translations:id,pageId,publishedAt')->first(['id']);
        $homePublishedAt = $home?->translations->pluck('publishedAt')->filter()->sortDesc()->first();
        $features = $settingsRow !== null && is_string($settingsRow->features ?? null) ? json_decode($settingsRow->features, true) : null;
        $lastNews = $canContent ? NewsPost::query()->max('createdAt') : null;
        $lastMedia = $canContent ? MediaAsset::query()->max('createdAt') : null;

        $checklist = self::buildChecklist([
            'setupAt' => $settingsRow !== null && $settingsRow->createdAt !== null ? Carbon::parse($settingsRow->createdAt, 'UTC') : null,
            'canSettings' => $canSettings,
            'canContent' => $canContent,
            'canPages' => Permissions::can($role, 'pages.editDraft'),
            'todoCount' => $todoCount,
            'demoContent' => (bool) ($features['demoContent'] ?? false),
            'lastNewsCreatedAt' => $lastNews !== null ? Carbon::parse($lastNews, 'UTC') : null,
            'lastMediaCreatedAt' => $lastMedia !== null ? Carbon::parse($lastMedia, 'UTC') : null,
            'homePublishedAt' => $homePublishedAt,
            'homeId' => $home?->id,
        ]);

        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role->value],
            'pages' => $pages,
            'news' => $news,
            'events' => $events,
            'mediaCount' => MediaAsset::query()->count(),
            'projectsActive' => Project::query()->where('status', 'PUBLISHED')->count(),
            'submissions' => $submissions,
            'donations' => $donations,
            'activity' => $activity,
            'todoCount' => $todoCount,
            'drafts' => $drafts,
            'checklist' => $checklist,
            'homePageId' => $home?->id,
            // Pages the site cannot work without; offered on the dashboard when an install has no content import.
            'missingSystemPages' => Permissions::can($role, 'pages.create')
                ? array_values(array_diff(['home', 'privacy'], Page::query()->whereIn('key', ['home', 'privacy'])->pluck('key')->all()))
                : [],
        ];
    }
}
