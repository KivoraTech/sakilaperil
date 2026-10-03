<?php

declare(strict_types=1);

namespace App\Install;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\Passwords;
use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Creating the very first SUPER_ADMIN on a fresh install (the shipped SQL dump contains no users).
 * Two entry points use this: `php artisan sakila:create-admin` (SSH/Terminal) and the one-time
 * /install page (no SSH; needs INSTALL_TOKEN in .env). Both stop working as soon as an active
 * SUPER_ADMIN exists, so the install page can never be used to take over a running site.
 */
final class FirstAdmin
{
    public const PASSWORD_MIN_LENGTH = 12;

    public const PASSWORD_MAX_LENGTH = 128;

    public static function needed(): bool
    {
        return ! User::query()->where('role', Role::SUPER_ADMIN->value)->where('isActive', true)->exists();
    }

    /**
     * @return array<string, string> field => error (empty when valid)
     */
    public static function validate(string $name, string $email, string $password): array
    {
        $errors = [];
        if (trim($name) === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Enter a name (up to 120 characters).';
        }
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 200) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (User::query()->where('email', $email)->exists()) {
            $errors['email'] = 'A user with this email already exists.';
        }
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH || mb_strlen($password) > self::PASSWORD_MAX_LENGTH) {
            $errors['password'] = 'Use '.self::PASSWORD_MIN_LENGTH.'–'.self::PASSWORD_MAX_LENGTH.' characters.';
        } elseif (($weak = Passwords::weakness($password)) !== null) {
            $errors['password'] = $weak;
        }

        return $errors;
    }

    /** Creates the first SUPER_ADMIN; throws if one already exists (re-checked under a lock). */
    public static function create(string $name, string $email, string $password, string $via): User
    {
        return Cache::lock('sakila:first-admin', 10)->block(5, function () use ($name, $email, $password, $via): User {
            if (! self::needed()) {
                throw new RuntimeException('An administrator already exists.');
            }
            $user = UserAccounts::create(trim($name), $email, $password, Role::SUPER_ADMIN);
            Audit::log(AuditAction::USER_CREATED, $user->id, 'User', $user->id, ['role' => Role::SUPER_ADMIN->value, 'via' => $via]);

            return $user;
        });
    }
}
