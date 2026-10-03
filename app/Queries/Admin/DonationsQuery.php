<?php

declare(strict_types=1);

namespace App\Queries\Admin;

use App\Auth\Permissions;
use App\Models\Donation;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Admin reads for Donations (ENGAGEMENT; port of lib/queries/admin/donations.ts). ADMIN+ (`donations.read`). */
final class DonationsQuery
{
    public const PROVIDERS = ['STRIPE', 'PAYPAL', 'ESEWA', 'KHALTI', 'BANK_TRANSFER'];

    public const STATUSES = ['PENDING', 'SUCCEEDED', 'FAILED', 'CANCELED', 'REFUNDED'];

    public const PAGE_SIZE = 30;

    public static function authorize(?User $user, string $capability = 'donations.read'): User
    {
        if (! $user instanceof User || ! Permissions::can($user->role, $capability)) {
            throw new AuthorizationException;
        }

        return $user;
    }

    /**
     * listDonationsSchema with Zod `.catch()` semantics.
     *
     * @param  array<string, mixed>  $q
     * @return array{provider?: string, status?: string, mode: string, from?: string, to?: string, q?: string, page: int}
     */
    public static function params(array $q): array
    {
        $out = ['mode' => in_array($q['mode'] ?? null, ['live', 'test', 'all'], true) ? $q['mode'] : 'all'];
        if (in_array($q['provider'] ?? null, self::PROVIDERS, true)) {
            $out['provider'] = $q['provider'];
        }
        if (in_array($q['status'] ?? null, self::STATUSES, true)) {
            $out['status'] = $q['status'];
        }
        foreach (['from', 'to'] as $k) {
            if (is_string($q[$k] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q[$k]) === 1) {
                $out[$k] = $q[$k];
            }
        }
        if (is_string($q['q'] ?? null) && trim($q['q']) !== '' && mb_strlen(trim($q['q'])) <= 200) {
            $out['q'] = trim($q['q']);
        }
        $page = filter_var($q['page'] ?? 1, FILTER_VALIDATE_INT);
        $out['page'] = is_int($page) && $page >= 1 && $page <= 10_000 ? $page : 1;

        return $out;
    }

    /**
     * @param  array<string, mixed>  $p
     * @return Builder<Donation>
     */
    public static function query(array $p): Builder
    {
        $fmt = static fn (string $d, int $addDays = 0) => CarbonImmutable::parse("{$d} 00:00:00", 'UTC')->addDays($addDays)->format('Y-m-d H:i:s.v');

        return Donation::query()
            ->when(isset($p['provider']), fn ($q) => $q->where('provider', $p['provider']))
            ->when(isset($p['status']), fn ($q) => $q->where('status', $p['status']))
            ->when($p['mode'] === 'live', fn ($q) => $q->where('isTestMode', false))
            ->when($p['mode'] === 'test', fn ($q) => $q->where('isTestMode', true))
            ->when(isset($p['from']), fn ($q) => $q->where('createdAt', '>=', $fmt($p['from'])))
            ->when(isset($p['to']), fn ($q) => $q->where('createdAt', '<', $fmt($p['to'], 1)))
            ->when(isset($p['q']), function ($q) use ($p) {
                $like = '%'.addcslashes((string) $p['q'], '%_\\').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $like)->orWhere('donorEmail', 'like', $like)->orWhere('donorName', 'like', $like));
            });
    }

    /**
     * @param  array<string, mixed>  $p
     * @return array<string, mixed>
     */
    public static function list(array $p): array
    {
        $query = self::query($p);
        $total = (clone $query)->count();
        $rows = (clone $query)->orderByDesc('createdAt')->skip(($p['page'] - 1) * self::PAGE_SIZE)->take(self::PAGE_SIZE)
            ->with(['project.translations' => fn ($t) => $t->where('locale', 'it')])->get();
        // Totals only count money that actually arrived.
        $totals = (clone $query)->toBase()->where('status', 'SUCCEEDED')->groupBy('currency')
            ->select('currency', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as gifts'))->get();

        return [
            'items' => $rows->map(fn (Donation $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'provider' => $d->provider->value,
                'status' => $d->status->value,
                'amount' => $d->amount,
                'currency' => $d->currency,
                'donorName' => $d->donorName,
                'donorEmail' => $d->donorEmail,
                'isAnonymous' => $d->isAnonymous,
                'isTestMode' => $d->isTestMode,
                'createdAt' => SubmissionsQuery::iso($d->createdAt),
                'monthly' => (is_array($d->providerData) ? ($d->providerData['frequency'] ?? null) : null) === 'monthly',
                'projectTitle' => $d->project?->translations->first()?->title,
            ])->values()->all(),
            'total' => $total,
            'page' => $p['page'],
            'pageCount' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'totals' => $totals->map(fn (object $t) => ['currency' => (string) $t->currency, 'amount' => (int) $t->total, 'count' => (int) $t->gifts])->values()->all(),
        ];
    }

    /**
     * Active projects for the "record bank transfer" form.
     *
     * @return list<array{id: string, title: string}>
     */
    public static function projectOptions(): array
    {
        return Project::query()->where('status', '!=', 'ARCHIVED')->orderByDesc('createdAt')->take(200)
            ->with(['translations' => fn ($t) => $t->where('locale', 'it')])->get()
            ->map(fn (Project $p) => ['id' => $p->id, 'title' => $p->translations->isNotEmpty() ? $p->translations->first()->title : $p->id])->values()->all();
    }
}
