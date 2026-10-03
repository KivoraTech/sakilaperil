<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ProductionConfig;
use Illuminate\Console\Command;

/**
 * `php artisan sakila:config-check` — the settings the live site needs (.env). Exit code 1 when
 * something must be fixed; warnings do not fail. Used by scripts/cpanel/sakila-deploy.sh before
 * a new version goes live.
 */
final class ConfigCheck extends Command
{
    protected $signature = 'sakila:config-check';

    protected $description = 'Check the .env settings the live site needs (URLs, database, uploads folder, email)';

    public function handle(): int
    {
        $errors = ProductionConfig::errors();
        $warnings = ProductionConfig::warnings();
        foreach ($errors as $e) {
            $this->line('ERROR  '.$e);
        }
        foreach ($warnings as $w) {
            $this->line('WARN   '.$w);
        }
        if ($errors === []) {
            $this->info('Settings OK'.($warnings === [] ? '.' : ' ('.count($warnings).' warning'.(count($warnings) === 1 ? '' : 's').').'));

            return self::SUCCESS;
        }
        $this->error(count($errors).' setting'.(count($errors) === 1 ? '' : 's').' must be fixed in .env.');

        return self::FAILURE;
    }
}
