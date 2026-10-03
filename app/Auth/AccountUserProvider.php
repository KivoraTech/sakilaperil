<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Users come from `users`; the password hash from `accounts` (providerId "credential"), so the
 * schema stays identical to the reference app. Deactivated users can never authenticate.
 */
final class AccountUserProvider extends EloquentUserProvider
{
    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = mb_strtolower(trim((string) ($credentials['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        return User::query()->where('email', $email)->where('isActive', true)->first();
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return Passwords::verify(UserAccounts::passwordHash((string) $user->getAuthIdentifier()), (string) ($credentials['password'] ?? ''));
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        $hash = UserAccounts::passwordHash((string) $user->getAuthIdentifier());
        if ($hash !== null && ($force || Passwords::needsRehash($hash))) {
            UserAccounts::storeRehash((string) $user->getAuthIdentifier(), (string) $credentials['password']);
        }
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        $user = parent::retrieveById($identifier);

        // `role` is a MySQL ENUM cast to App\Enums\Role, so an unknown role cannot load at all.
        return $user instanceof User && $user->isActive ? $user : null;
    }
}
