<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\Permissions;
use App\Auth\UserAccounts;
use App\Cache\CacheTags;
use App\Cache\TagCache;
use App\Enums\Role;
use App\Http\Actions\ActionResult;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pages\Input;
use App\Pages\Json;
use App\Queries\Admin\PagesQuery;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

/**
 * User management (reference app/admin/(shell)/users + lib/actions/users.ts + lib/users/rules.ts).
 * Public sign-up is disabled: admins create accounts here with a temporary password shown ONCE.
 * "Last active super admin" rules run in a SERIALIZABLE transaction with deadlock retry.
 */
final class UsersController extends Controller
{
    public const PASSWORD_MIN_LENGTH = 12;

    public const PASSWORD_MAX_LENGTH = 128;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        PagesQuery::authorize($actor, 'users.manage');

        return Inertia::render('admin/users/index', [
            ...self::listUsers(),
            'actor' => ['id' => $actor->id, 'role' => $actor->role->value],
        ]);
    }

    /** @return array{users: list<array<string, mixed>>, activeSuperAdmins: int} */
    public static function listUsers(): array
    {
        $table = (string) config('session.table');
        $cutoff = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
        $sessions = DB::table($table)->whereNotNull('user_id')->where('last_activity', '>=', $cutoff)
            ->groupBy('user_id')->pluck(DB::raw('COUNT(*)'), 'user_id');
        $rows = User::query()->orderByDesc('isActive')->orderBy('name')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'isActive' => (bool) $u->isActive,
                'lastLoginAt' => Json::iso($u->lastLoginAt),
                'createdAt' => Json::iso($u->createdAt),
                'activeSessions' => (int) ($sessions[$u->id] ?? 0),
            ])->values()->all();

        return [
            'users' => $rows,
            'activeSuperAdmins' => count(array_filter($rows, fn ($u) => $u['role'] === 'SUPER_ADMIN' && $u['isActive'])),
        ];
    }

    // ───────────────────────────── actions ─────────────────────────────

    public function create(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $body = Json::object(Input::body($request));
            $name = Input::text($body->name ?? '');
            $email = mb_strtolower(Input::text($body->email ?? ''));
            $role = Role::tryFrom(is_string($body->role ?? null) ? $body->role : '');
            $password = is_string($body->password ?? null) ? $body->password : '';
            $errors = [];
            if ($name === '' || mb_strlen($name) > 100) {
                $errors['name'] = ['Enter a name'];
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 200) {
                $errors['email'] = ['Enter a valid email'];
            }
            if ($role === null) {
                $errors['role'] = ['Invalid option'];
            }
            if ($password !== '' && ($error = self::passwordError($password)) !== null) {
                $errors['password'] = [$error];
            }
            if ($errors !== [] || $role === null) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', $errors);
            }
            if (! Permissions::canAssignRole($actor->role, $role)) {
                return ActionResult::fail('FORBIDDEN', 'You cannot create users with this role.');
            }
            if (User::query()->where('email', $email)->exists()) {
                return ActionResult::fail('CONFLICT', 'A user with this email already exists.', ['email' => ['Already in use']]);
            }
            $generated = $password === '' ? self::temporaryPassword() : null;
            try {
                $user = UserAccounts::create($name, $email, $password !== '' ? $password : (string) $generated, $role);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    return ActionResult::fail('CONFLICT', 'A user with this email already exists.');
                }
                throw $e;
            }
            Audit::log(AuditAction::USER_CREATED, $actor->id, 'User', $user->id, [
                'email' => $email, 'role' => $role->value, 'temporaryPassword' => $generated !== null,
            ]);

            return ActionResult::ok(['id' => $user->id, 'temporaryPassword' => $generated], 'User created');
        });
    }

    public function changeRole(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $body = Json::object(Input::body($request));
            $role = Role::tryFrom(is_string($body->role ?? null) ? $body->role : '');
            if ($role === null) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['role' => ['Invalid option']]);
            }
            $target = $this->target($body);
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'User not found.');
            }
            if ($target->role === $role) {
                return ActionResult::ok(null, 'No change');
            }
            // Serialize the "last super admin" check with the update (two admins demoting each other at once).
            $rule = self::serializable(function () use ($actor, $target, $role) {
                $count = User::query()->where('role', 'SUPER_ADMIN')->where('isActive', true)->count();
                $rule = self::checkRoleChange($actor, $target, $role, $count);
                if ($rule !== null) {
                    return $rule;
                }
                User::query()->whereKey($target->id)->update(['role' => $role->value]);
                UserAccounts::revokeSessions($target->id); // new permissions apply on next sign-in

                return null;
            });
            if ($rule !== null) {
                return ActionResult::fail($rule[0], $rule[1]);
            }
            Audit::log(AuditAction::USER_ROLE_CHANGED, $actor->id, 'User', $target->id, [
                'email' => $target->email, 'from' => $target->role->value, 'to' => $role->value,
            ]);

            return ActionResult::ok(null, 'Role changed to '.Permissions::ROLE_LABELS[$role->value].'. They were signed out everywhere.');
        });
    }

    public function setActive(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $body = Json::object(Input::body($request));
            if (! is_bool($body->active ?? null)) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['active' => ['Invalid input']]);
            }
            $active = $body->active;
            $target = $this->target($body);
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'User not found.');
            }
            if ((bool) $target->isActive === $active) {
                return ActionResult::ok(null, 'No change');
            }
            $rule = self::serializable(function () use ($actor, $target, $active) {
                $count = User::query()->where('role', 'SUPER_ADMIN')->where('isActive', true)->count();
                $rule = self::checkSetActive($actor, $target, $active, $count);
                if ($rule !== null) {
                    return $rule;
                }
                User::query()->whereKey($target->id)->update(['isActive' => $active]);
                if (! $active) {
                    UserAccounts::revokeSessions($target->id);
                }

                return null;
            });
            if ($rule !== null) {
                return ActionResult::fail($rule[0], $rule[1]);
            }
            Audit::log($active ? AuditAction::USER_UPDATED : AuditAction::USER_DEACTIVATED, $actor->id, 'User', $target->id, [
                'email' => $target->email, 'active' => $active,
            ]);

            return ActionResult::ok(null, $active ? 'User reactivated' : 'User deactivated and signed out');
        });
    }

    public function revokeSessions(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $target = $this->target(Json::object(Input::body($request)));
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'User not found.');
            }
            if (($rule = self::checkManageOrSelf($actor, $target)) !== null) {
                return ActionResult::fail($rule[0], $rule[1]);
            }
            $count = DB::table((string) config('session.table'))->where('user_id', $target->id)->count();
            UserAccounts::revokeSessions($target->id);
            Audit::log(AuditAction::USER_UPDATED, $actor->id, 'User', $target->id, [
                'email' => $target->email, 'sessionsRevoked' => $count,
            ]);

            return ActionResult::ok(['count' => $count], $count > 0 ? "Signed out of {$count} session(s)" : 'No active sessions');
        });
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $body = Json::object(Input::body($request));
            $password = is_string($body->password ?? null) ? $body->password : '';
            if ($password !== '' && ($error = self::passwordError($password)) !== null) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['password' => [$error]]);
            }
            $target = $this->target($body);
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'User not found.');
            }
            if (($rule = self::checkManageOrSelf($actor, $target)) !== null) {
                return ActionResult::fail($rule[0], $rule[1]);
            }
            $generated = $password === '' ? self::temporaryPassword() : null;
            UserAccounts::setPassword($target->id, $password !== '' ? $password : (string) $generated);
            Audit::log(AuditAction::USER_PASSWORD_RESET, $actor->id, 'User', $target->id, [
                'email' => $target->email, 'temporaryPassword' => $generated !== null,
            ]);

            return ActionResult::ok(['temporaryPassword' => $generated], 'Password reset — all their sessions were signed out');
        });
    }

    public function rename(Request $request): JsonResponse
    {
        return ActionResult::run(function () use ($request) {
            $actor = $this->actor($request);
            $body = Json::object(Input::body($request));
            $name = Input::text($body->name ?? '');
            if ($name === '' || mb_strlen($name) > 100) {
                return ActionResult::fail('VALIDATION', 'Please check the highlighted fields.', ['name' => ['Enter a name']]);
            }
            $target = $this->target($body);
            if ($target === null) {
                return ActionResult::fail('NOT_FOUND', 'User not found.');
            }
            if (($rule = self::checkManageOrSelf($actor, $target)) !== null) {
                return ActionResult::fail($rule[0], $rule[1]);
            }
            User::query()->whereKey($target->id)->update(['name' => $name]);
            Audit::log(AuditAction::USER_UPDATED, $actor->id, 'User', $target->id, ['from' => $target->name, 'to' => $name]);
            // The author's name is cached with the public news pages.
            TagCache::invalidate(CacheTags::NEWS);

            return ActionResult::ok(null, 'Name updated');
        });
    }

    // ───────────────────────────── rules (lib/users/rules.ts) ─────────────────────────────

    /** @return array{0: string, 1: string}|null [code, message] when denied */
    public static function checkRoleChange(User $actor, User $target, Role $newRole, int $activeSuperAdmins): ?array
    {
        if ($actor->id === $target->id) {
            return ['FORBIDDEN', 'You cannot change your own role. Ask another admin.'];
        }
        if (! Permissions::canManageUser($actor->role, $target->role)) {
            return ['FORBIDDEN', 'You cannot manage this user.'];
        }
        if (! Permissions::canAssignRole($actor->role, $newRole)) {
            return ['FORBIDDEN', 'You cannot assign this role.'];
        }
        if ($target->role === Role::SUPER_ADMIN && $newRole !== Role::SUPER_ADMIN && $target->isActive && $activeSuperAdmins <= 1) {
            return ['CONFLICT', 'This is the last super admin. Make someone else super admin first.'];
        }

        return null;
    }

    /** @return array{0: string, 1: string}|null */
    public static function checkSetActive(User $actor, User $target, bool $active, int $activeSuperAdmins): ?array
    {
        if ($actor->id === $target->id && ! $active) {
            return ['FORBIDDEN', 'You cannot deactivate your own account.'];
        }
        if (! Permissions::canManageUser($actor->role, $target->role)) {
            return ['FORBIDDEN', 'You cannot manage this user.'];
        }
        if (! $active && $target->role === Role::SUPER_ADMIN && $target->isActive && $activeSuperAdmins <= 1) {
            return ['CONFLICT', 'This is the last active super admin and cannot be deactivated.'];
        }

        return null;
    }

    /**
     * Revoke sessions / reset password / rename: self is always allowed, others need canManageUser.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function checkManageOrSelf(User $actor, User $target): ?array
    {
        if ($actor->id === $target->id) {
            return null;
        }

        return Permissions::canManageUser($actor->role, $target->role) ? null : ['FORBIDDEN', 'You cannot manage this user.'];
    }

    /** Readable random temporary password (no ambiguous characters), 20 chars ≈ 115 bits. */
    public static function temporaryPassword(): string
    {
        $out = '';
        while (strlen($out) < 20) {
            foreach (str_split(random_bytes(40)) as $byte) {
                $b = ord($byte);
                if ($b >= 232) {
                    continue; // 232 = 4 × 58 → unbiased modulo
                }
                $out .= self::ALPHABET[$b % strlen(self::ALPHABET)];
                if (strlen($out) === 20) {
                    break;
                }
            }
        }

        return implode('-', str_split($out, 5));
    }

    public static function passwordError(string $password): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return 'Use at least '.self::PASSWORD_MIN_LENGTH.' characters';
        }
        if (mb_strlen($password) > self::PASSWORD_MAX_LENGTH) {
            return 'Use at most '.self::PASSWORD_MAX_LENGTH.' characters';
        }

        return null;
    }

    /**
     * SERIALIZABLE transaction for the "last active super admin" rules. On MySQL/MariaDB two
     * concurrent transactions take shared locks on the counted rows and one is rolled back as a
     * deadlock (1213) or lock wait timeout (1205). Retry: the retry sees the committed state.
     *
     * @template T
     *
     * @param  Closure(): T  $fn
     * @return T
     */
    public static function serializable(Closure $fn): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                // Applies to the next transaction only; inside an outer transaction (tests) it cannot change.
                if (DB::transactionLevel() === 0) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                }

                return DB::transaction($fn);
            } catch (QueryException $e) {
                $retryable = in_array($e->errorInfo[1] ?? null, [1213, 1205], true);
                if (! $retryable || $attempt >= 3) {
                    throw $e;
                }
                usleep((20 * $attempt + random_int(0, 30)) * 1000);
            }
        }
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function target(stdClass $body): ?User
    {
        $id = $body->userId ?? null;
        if (! is_string($id) || $id === '' || mb_strlen($id) > 64) {
            return null;
        }

        return User::query()->find($id);
    }
}
