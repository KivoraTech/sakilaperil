<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Auth\Permissions;
use App\Models\ContactSubmission;
use App\Models\MembershipApplication;
use App\Models\NewsletterSubscriber;
use App\Models\PartnerInquiry;
use App\Models\User;
use App\Models\VolunteerApplication;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin reads for the Submissions inbox (ENGAGEMENT; port of lib/queries/admin/submissions.ts).
 * Personal data: every read requires `submissions.manage` (ADMIN+), like the reference.
 */
final class SubmissionsQuery
{
    public const CAPABILITY = 'submissions.manage';

    public const INBOX_TYPES = ['contact', 'volunteer', 'membership', 'partner', 'newsletter'];

    public const SUBMISSION_TYPES = ['contact', 'volunteer', 'membership', 'partner'];

    public const INBOX_VIEWS = ['inbox', 'unread', 'archived', 'all'];

    public const NEWSLETTER_VIEWS = ['all', 'PENDING', 'SUBSCRIBED', 'UNSUBSCRIBED'];

    public const PAGE_SIZE = 25;

    /** @var array<string, class-string<Model>> */
    public const MODELS = [
        'contact' => ContactSubmission::class,
        'volunteer' => VolunteerApplication::class,
        'membership' => MembershipApplication::class,
        'partner' => PartnerInquiry::class,
        'newsletter' => NewsletterSubscriber::class,
    ];

    public static function authorize(?User $user): User
    {
        if (! $user instanceof User || ! Permissions::can($user->role, self::CAPABILITY)) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * listSubmissionsSchema with Zod `.catch()` semantics.
     *
     * @param  array<string, mixed>  $q
     * @return array{type: string, view: ?string, q: ?string, page: int}
     */
    public static function params(array $q): array
    {
        $type = is_string($q['type'] ?? null) && in_array($q['type'], self::INBOX_TYPES, true) ? $q['type'] : 'contact';
        $view = is_string($q['view'] ?? null) && mb_strlen($q['view']) <= 20 ? $q['view'] : null;
        $search = is_string($q['q'] ?? null) ? trim($q['q']) : '';
        $page = filter_var($q['page'] ?? 1, FILTER_VALIDATE_INT);

        return [
            'type' => $type,
            'view' => $view,
            'q' => $search !== '' && mb_strlen($search) <= 200 ? $search : null,
            'page' => is_int($page) && $page >= 1 && $page <= 10_000 ? $page : 1,
        ];
    }

    /** @return Builder<Model> */
    public static function query(string $type, ?string $view, ?string $q): Builder
    {
        /** @var Builder<Model> $query */
        $query = (self::MODELS[$type])::query();
        if ($type === 'newsletter') {
            if ($view !== null && $view !== 'all' && in_array($view, self::NEWSLETTER_VIEWS, true)) {
                $query->where('status', $view);
            }
        } else {
            $archived = $type === 'contact' ? ['ARCHIVED', 'SPAM'] : ['ARCHIVED'];
            match ($view ?? 'inbox') {
                'unread' => $query->where('status', 'NEW'),
                'archived' => $query->whereIn('status', $archived),
                'all' => null,
                default => $query->whereNotIn('status', $archived),
            };
        }
        if ($q !== null) {
            $columns = match ($type) {
                'contact' => ['name', 'email', 'message'],
                'partner' => ['organization', 'contactName', 'email'],
                'newsletter' => ['email'],
                default => ['firstName', 'lastName', 'email'],
            };
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function (Builder $w) use ($columns, $like) {
                foreach ($columns as $c) {
                    $w->orWhere($c, 'like', $like);
                }
            });
        }

        return $query;
    }

    private static function clip(?string $s, int $n = 120): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        // JS String.length/slice count UTF-16 units; submissions are mostly BMP text.
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n).'…' : $s;
    }

    /** @return array{id: string, name: string, email: string, preview: string, status: string, unread: bool, locale: string, createdAt: string} */
    public static function toRow(string $type, Model $r): array
    {
        $s = static fn (string $k): string => is_string($v = $r->getAttribute($k)) ? $v : (is_object($v) && property_exists($v, 'value') ? (string) $v->value : '');
        $name = match ($type) {
            'contact' => $s('name'),
            'partner' => $s('organization').' · '.$s('contactName'),
            'newsletter' => $s('email'),
            default => $s('firstName').' '.$s('lastName'),
        };
        $preview = match ($type) {
            'contact', 'partner' => self::clip($s('message')),
            'membership' => $s('membershipType'),
            'volunteer' => self::clip($s('skills') ?: ($s('message') ?: $s('availability'))),
            default => implode(' · ', array_filter([$s('source'), $s('syncError') !== '' ? 'sync error: '.$s('syncError') : ''])),
        };
        $status = $s('status');

        return [
            'id' => $s('id'),
            'name' => $name,
            'email' => $s('email'),
            'preview' => $preview,
            'status' => $status,
            'unread' => $type !== 'newsletter' && $status === 'NEW',
            'locale' => $s('locale'),
            'createdAt' => self::iso($r->getAttribute('createdAt')),
        ];
    }

    public static function iso(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s.v\Z') : '';
    }

    /**
     * @param  array{type: string, view: ?string, q: ?string, page: int}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pageCount: int}
     */
    public static function list(array $p): array
    {
        $query = self::query($p['type'], $p['view'], $p['q']);
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('createdAt')->skip(($p['page'] - 1) * self::PAGE_SIZE)->take(self::PAGE_SIZE)->get();

        return [
            'items' => $rows->map(fn (Model $r) => self::toRow($p['type'], $r))->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
        ];
    }

    /**
     * Unread (NEW) / pending counts per type for the tab badges.
     *
     * @return array<string, int>
     */
    public static function unreadCounts(): array
    {
        return [
            'contact' => ContactSubmission::query()->where('status', 'NEW')->count(),
            'volunteer' => VolunteerApplication::query()->where('status', 'NEW')->count(),
            'membership' => MembershipApplication::query()->where('status', 'NEW')->count(),
            'partner' => PartnerInquiry::query()->where('status', 'NEW')->count(),
            'newsletter' => NewsletterSubscriber::query()->where('status', 'PENDING')->count(),
        ];
    }

    public static function find(string $type, string $id): ?Model
    {
        if (! in_array($type, self::SUBMISSION_TYPES, true) || preg_match('/^[a-z0-9]{10,40}$/i', $id) !== 1) {
            return null;
        }

        return (self::MODELS[$type])::query()->with('handledBy:id,name,email')->find($id);
    }
}
