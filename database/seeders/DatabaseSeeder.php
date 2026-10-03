<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Port of sakila/prisma/seed.ts. `php artisan db:seed` (or `migrate:fresh --seed`):
 * - creates the seed users (SEED_ADMIN_*; demo editor/author outside production),
 * - REPLACES all content (pages, projects, news, events, gallery, reports, navigation, settings,
 *   seeded media) with the placeholder content of the reference app,
 * - never touches other users, submissions, donations or the audit log.
 * Refuses production unless SEED_ALLOW_PRODUCTION=true and a non-default admin password is set.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            if (! filter_var(config('sakila.seed.allow_production'), FILTER_VALIDATE_BOOL)) {
                throw new RuntimeException('Refusing to seed in production (set SEED_ALLOW_PRODUCTION=true to override).');
            }
            $pw = (string) config('sakila.seed.admin_password');
            if ($pw === '' || $pw === UsersSeeder::DEFAULT_PASSWORD) {
                throw new RuntimeException('Set a strong, non-default SEED_ADMIN_PASSWORD to seed in production.');
            }
        }
        $this->call(UsersSeeder::class);
        ContentSeeder::$adminId = UsersSeeder::$adminId;
        $this->call(ContentSeeder::class);
    }
}
