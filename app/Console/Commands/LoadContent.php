<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Real\ContentLoader;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;

/**
 * `php artisan sakila:load-content` — load the association's real content (database/content)
 * into an EMPTY site; `--fresh` replaces the existing pages, projects, news, events, albums,
 * menus and settings first. Users, form submissions, donations, newsletter subscribers and the
 * audit history are never touched; photos uploaded in the admin are kept.
 */
final class LoadContent extends Command
{
    use ConfirmableTrait;

    protected $signature = 'sakila:load-content
        {--fresh : Replace existing pages, projects, news, events, albums, menus and settings}
        {--force : Do not ask for confirmation in production}
        {--path= : Dataset folder (default: database/content)}';

    protected $description = 'Load the real content dataset (database/content) through the CMS, as an administrator would';

    public function handle(): int
    {
        $dir = (string) ($this->option('path') ?: database_path('content'));
        $fresh = (bool) $this->option('fresh');

        if (! $fresh && ContentLoader::hasContent()) {
            $this->error('This site already has content. Nothing was changed.');
            $this->line('To REPLACE pages, projects, news, events, albums, menus and settings with the dataset, run:');
            $this->line('  php artisan sakila:load-content --fresh');

            return self::FAILURE;
        }
        if ($fresh && ContentLoader::hasContent() && ! $this->confirmToProceed('This replaces the site\'s pages, projects, news, events, albums, menus and settings.')) {
            return self::FAILURE;
        }

        try {
            $counts = (new ContentLoader($dir, function (string $line): void {
                if ($this->output->isVerbose()) {
                    $this->line($line);
                }
            }))->load($fresh);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Content loaded: '.implode(', ', array_map(fn (string $k, int $v): string => "{$v} {$k}", array_keys($counts), $counts)).'.');
        $this->line('Check what is still missing with: php artisan sakila:content-check');

        return self::SUCCESS;
    }
}
