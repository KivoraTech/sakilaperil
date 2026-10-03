<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Install\FirstAdmin;
use Illuminate\Console\Command;
use Throwable;

/** `php artisan sakila:create-admin` — first SUPER_ADMIN on a fresh install (SSH/Terminal). */
final class CreateAdmin extends Command
{
    protected $signature = 'sakila:create-admin {email : Login email} {--name= : Display name}';

    protected $description = 'Create the first super administrator (only while none exists)';

    public function handle(): int
    {
        if (! FirstAdmin::needed()) {
            $this->error('An active super administrator already exists. Add more users in Admin → Users.');

            return self::FAILURE;
        }
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: strstr($email, '@', true) ?: 'Administrator');
        $password = (string) $this->secret('Password (12+ characters, input hidden)');
        if (($errors = FirstAdmin::validate($name, $email, $password)) !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        try {
            FirstAdmin::create($name, $email, $password, 'artisan');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Super administrator {$email} created. Sign in at /admin/login.");

        return self::SUCCESS;
    }
}
