<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Seed users (port of seedUsers in sakila/prisma/seed.ts). Existing users are left untouched. */
class UsersSeeder extends Seeder
{
    /** The password documented in README/.env.example: for local development only. */
    public const DEFAULT_PASSWORD = 'ChangeMe-Sakila-2026!';

    public static string $adminId = '';

    public function run(): void
    {
        $seed = config('sakila.seed');
        // Also when this seeder is run on its own (`db:seed --class=UsersSeeder`): the documented
        // default password must never create an administrator on a live site.
        if (app()->isProduction()) {
            if (! filter_var($seed['allow_production'], FILTER_VALIDATE_BOOL)) {
                throw new RuntimeException('Refusing to seed users in production (create the first administrator with /install or `php artisan sakila:create-admin`; SEED_ALLOW_PRODUCTION=true overrides).');
            }
            $pw = (string) $seed['admin_password'];
            if ($pw === '' || $pw === self::DEFAULT_PASSWORD || mb_strlen($pw) < 12) {
                throw new RuntimeException('Set a strong, non-default SEED_ADMIN_PASSWORD (12+ characters) to seed users in production.');
            }
        }
        $users = [[
            'name' => (string) $seed['admin_name'],
            'email' => (string) $seed['admin_email'],
            'password' => (string) $seed['admin_password'],
            'role' => Role::SUPER_ADMIN,
        ]];
        // Extra role accounts for local testing of permissions (never in production).
        if (! app()->isProduction() && filter_var($seed['demo_users'], FILTER_VALIDATE_BOOL)) {
            $pw = (string) $seed['demo_password'];
            $users[] = ['name' => 'Elena Editor', 'email' => 'editor@sakila.local', 'password' => $pw, 'role' => Role::EDITOR];
            $users[] = ['name' => 'Arjun Author', 'email' => 'author@sakila.local', 'password' => $pw, 'role' => Role::AUTHOR];
        }
        foreach ($users as $u) {
            $existing = User::query()->where('email', mb_strtolower($u['email']))->first();
            $user = $existing ?? UserAccounts::create($u['name'], $u['email'], $u['password'], $u['role']);
            if ($u['role'] === Role::SUPER_ADMIN) {
                self::$adminId = (string) $user->id;
            }
            $this->info(($existing ? 'exists ' : 'created ').$u['role']->value.' '.$u['email']);
        }
    }

    /** Progress line when run from Artisan; silent in tests (no console command is attached there). */
    private function info(string $message): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, '  '.$message.PHP_EOL);
        }
    }
}
