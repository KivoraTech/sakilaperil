<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Pages\Json;
use App\Queries\Admin\PagesQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only audit log (reference app/admin/(shell)/audit + lib/queries/admin/audit.ts). */
final class AuditController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        PagesQuery::authorize($user, 'audit.read');
        $params = self::params($request->query());

        return Inertia::render('admin/audit/index', ['params' => $params, 'data' => self::list($params)]);
    }

    /**
     * auditParamsSchema with Zod `.catch()` semantics (bad values are dropped).
     *
     * @param  array<string, mixed>  $q
     * @return array<string, string|int>
     */
    public static function params(array $q): array
    {
        $out = [];
        if (is_string($q['user'] ?? null) && $q['user'] !== '' && mb_strlen($q['user']) <= 64) {
            $out['user'] = $q['user'];
        }
        if (is_string($q['action'] ?? null) && AuditAction::tryFrom($q['action']) !== null) {
            $out['action'] = $q['action'];
        }
        if (is_string($q['entity'] ?? null) && preg_match('/^[A-Za-z]{1,40}$/', $q['entity']) === 1) {
            $out['entity'] = $q['entity'];
        }
        if (is_string($q['q'] ?? null) && trim($q['q']) !== '' && mb_strlen(trim($q['q'])) <= 64) {
            $out['q'] = trim($q['q']);
        }
        $page = filter_var($q['page'] ?? 1, FILTER_VALIDATE_INT);
        $out['page'] = is_int($page) && $page >= 1 && $page <= 10_000 ? $page : 1;

        return $out;
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array<string, mixed>
     */
    public static function list(array $params): array
    {
        $query = AuditLog::query()
            ->when(isset($params['user']), fn ($q) => $q->where('userId', $params['user']))
            ->when(isset($params['action']), fn ($q) => $q->where('action', $params['action']))
            ->when(isset($params['entity']), fn ($q) => $q->where('entityType', $params['entity']))
            ->when(isset($params['q']), fn ($q) => $q->where('entityId', $params['q']));
        $total = (clone $query)->count();
        $page = (int) $params['page'];
        $rows = $query->orderByDesc('createdAt')->skip(($page - 1) * self::PER_PAGE)->take(self::PER_PAGE)
            ->with('user:id,name,email')->get();

        return [
            'total' => $total,
            'page' => $page,
            'pageCount' => max(1, (int) ceil($total / self::PER_PAGE)),
            'rows' => $rows->map(fn (AuditLog $r) => [
                'id' => $r->id,
                'createdAt' => Json::iso($r->createdAt),
                'action' => $r->action,
                'entityType' => $r->entityType,
                'entityId' => $r->entityId,
                'metadata' => Json::column($r, 'metadata'),
                'ipAddress' => $r->ipAddress,
                'userAgent' => $r->userAgent,
                'userId' => $r->userId,
                'user' => $r->user?->name,
                'userEmail' => $r->user?->email,
            ])->values()->all(),
            'users' => User::query()->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
            'entityTypes' => AuditLog::query()->whereNotNull('entityType')->distinct()->orderBy('entityType')->pluck('entityType')->values()->all(),
        ];
    }
}
