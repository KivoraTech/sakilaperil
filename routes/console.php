<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Scheduled tasks. On cPanel add ONE cron job (every 5 minutes is enough), see docs/deploy-cpanel.md:
//   (every 5 min)  cd ~/sakila && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
Schedule::command('sakila:housekeeping')->dailyAt('03:10')->withoutOverlapping();
