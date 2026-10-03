<?php

declare(strict_types=1);

namespace App\Auth;

use App\Enums\Role;
use App\Models\Account;
use App\Models\User;
use App\Support\Cuid;
use Illuminate\Support\Facades\DB;

/**
 * Port of sakila/lib/auth/users.ts. Users are created only by admins/seed (no public sign-up).
 * The credential lives in `accounts` (providerId "credential"), like the reference schema.
 * Callers do authorization (Permissions::canAssignRole) and audit logging.
 */
final class UserAccounts
{
    public const PROVIDER = 'credential';

    public static function create(string $name, string $email, string $password, Role $role): User
    {
        return DB::transaction(function () use ($name, $email, $password, $role): User {
            $user = User::query()->create([
                'id' => Cuid::make(),
                'name' => $name,
                'email' => mb_strtolower(trim($email)),
                'emailVerified' => true,
                'role' => $role,
                'isActive' => true,
            ]);
            Account::query()->create([
                'id' => Cuid::make(),
                'accountId' => $user->id,
                'providerId' => self::PROVIDER,
                'userId' => $user->id,
                'password' => Passwords::hash($password),
            ]);

            // Reload so DB defaults (image, lastLoginAt, timestamps) are present on the model.
            return $user->refresh();
        });
    }

    public static function passwordHash(string $userId): ?string
    {
        $hash = Account::query()->where('userId', $userId)->where('providerId', self::PROVIDER)->value('password');

        return is_string($hash) ? $hash : null;
    }

    /** Replace a user's password and revoke all their sessions. */
    public static function setPassword(string $userId, string $password): void
    {
        DB::transaction(function () use ($userId, $password): void {
            Account::query()->where('userId', $userId)->where('providerId', self::PROVIDER)
                ->update(['password' => Passwords::hash($password)]);
            self::revokeSessions($userId);
        });
    }

    /** Store an upgraded hash without touching sessions (login-time rehash). */
    public static function storeRehash(string $userId, string $password): void
    {
        Account::query()->where('userId', $userId)->where('providerId', self::PROVIDER)
            ->update(['password' => Passwords::hash($password)]);
    }

    /** Revoke every session of a user (deactivation, role change, password reset). */
    public static function revokeSessions(string $userId): void
    {
        DB::table((string) config('session.table'))->where('user_id', $userId)->delete();
    }
}
