<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Engagement\Housekeeping as EngagementHousekeeping;
use App\Support\ProductionConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Daily cleanup (port of the reference scripts/cron/housekeeping.ts + Laravel's own leftovers).
 * Runs from the scheduler (`schedule:run` cron) or by hand: `php artisan sakila:housekeeping`.
 * Never touches content, submissions or donations (a PENDING bank pledge can legitimately stay
 * pending for weeks; abandoned card checkouts are harmless and visible in the admin; donations are
 * accounting records).
 *
 * Retention (config/sakila.php `retention`, config/engagement via App\Engagement\Housekeeping):
 *  - audit log: IP address and browser are blanked after 90 days, entries are deleted after 24 months;
 *  - unconfirmed newsletter sign-ups and old payment webhook ids: see App\Engagement\Housekeeping;
 *  - resized image variants: unused for 90 days, and the folder is kept under IMG_CACHE_MAX_MB.
 */
final class Housekeeping extends Command
{
    protected $signature = 'sakila:housekeeping {--dry-run : Count only, delete nothing}';

    protected $description = 'Delete expired sessions, stale drafts and counters, old audit data, unconfirmed newsletter sign-ups, expired cache files and old image variants';

    private const IMAGE_VARIANT_MAX_AGE_DAYS = 90;

    public function handle(): int
    {
        $now = Carbon::now('UTC');
        $ms = fn (Carbon $c): string => $c->format('Y-m-d H:i:s.v');
        $dayAgo = $now->copy()->subDay();
        $dry = (bool) $this->option('dry-run');
        $run = function ($query) use ($dry): int {
            return $dry ? $query->count() : $query->delete();
        };

        $results = [
            'previewDrafts' => $run(DB::table('page_preview_drafts')->where('updatedAt', '<', $ms($dayAgo))),
            'authSessions' => $run(DB::table('sessions')->where('expiresAt', '<', $ms($now))),
            'httpSessions' => $run(DB::table((string) config('session.table'))
                ->where('last_activity', '<', $now->getTimestamp() - (int) config('session.lifetime') * 60)),
            'verifications' => $run(DB::table('verifications')->where('expiresAt', '<', $ms($now))),
            'loginThrottles' => $run(DB::table('login_throttles')->where('updatedAt', '<', $ms($dayAgo))
                ->where(fn ($q) => $q->whereNull('lockedUntil')->orWhere('lockedUntil', '<', $ms($now)))),
            'formRateLimits' => $run(DB::table('form_rate_limits')->where('windowStart', '<', $ms($dayAgo))),
            'authRateLimits' => $run(DB::table('rate_limits')->where('lastRequest', '<', $dayAgo->getTimestampMs())),
            'cacheFiles' => $this->pruneFileCache($dry),
            'imageVariants' => $this->pruneImageVariants($dry, $now->getTimestamp() - self::IMAGE_VARIANT_MAX_AGE_DAYS * 86400),
        ];

        // Audit log: who did what stays for `audit_months`; where from (IP, browser) only for
        // `audit_ip_days`, long enough to investigate an incident.
        $ipDays = (int) config('sakila.retention.audit_ip_days', 90);
        if ($ipDays > 0) {
            $q = DB::table('audit_logs')->where('createdAt', '<', $ms($now->copy()->subDays($ipDays)))
                ->where(fn ($w) => $w->whereNotNull('ipAddress')->orWhereNotNull('userAgent'));
            $results['auditIpsBlanked'] = $dry ? $q->count() : $q->update(['ipAddress' => null, 'userAgent' => null]);
        }
        $auditMonths = (int) config('sakila.retention.audit_months', 24);
        if ($auditMonths > 0) {
            $results['auditLogs'] = $run(DB::table('audit_logs')->where('createdAt', '<', $ms($now->copy()->subMonths($auditMonths))));
        }

        // Engagement data with its own rules (unconfirmed newsletter sign-ups, webhook event ids).
        foreach (EngagementHousekeeping::prune($dry) as $key => $count) {
            $results[$key] = $count;
        }

        // Daily reminder in the log of settings that should be looked at (e.g. INSTALL_TOKEN left
        // in .env after the first administrator was created).
        if (app()->isProduction()) {
            foreach (ProductionConfig::warnings() as $warning) {
                $this->warn('[housekeeping] '.$warning);
                Log::warning('[config] '.$warning);
            }
        }
        $this->info('[housekeeping] '.$now->toIso8601String().($dry ? ' (dry run) would delete ' : ' deleted ').json_encode($results));

        return self::SUCCESS;
    }

    /** The file cache never deletes expired entries on its own; each file starts with a 10-digit expiry timestamp. */
    private function pruneFileCache(bool $dry): int
    {
        if (config('cache.default') !== 'file') {
            return 0;
        }
        $dir = (string) config('cache.stores.file.path');
        if (! is_dir($dir)) {
            return 0;
        }
        $count = 0;
        $now = time();
        foreach (File::allFiles($dir) as $file) {
            $handle = @fopen($file->getPathname(), 'r');
            if ($handle === false) {
                continue;
            }
            $expires = (int) fread($handle, 10);
            fclose($handle);
            if ($expires > 0 && $expires < $now) {
                $count++;
                if (! $dry) {
                    @unlink($file->getPathname());
                }
            }
        }

        return $count;
    }

    /**
     * Resized image variants are regenerated on demand: drop the ones nobody requested for 90 days,
     * then the least recently used ones until the folder is under `sakila.img_cache_max_mb`
     * (a shared-hosting disk quota must never be eaten by a cache).
     */
    private function pruneImageVariants(bool $dry, int $olderThan): int
    {
        $dir = (string) config('sakila.img_cache_dir');
        if ($dir === '' || ! is_dir($dir)) {
            return 0;
        }
        $count = 0;
        $kept = [];
        foreach (File::allFiles($dir) as $file) {
            $used = max((int) $file->getATime(), (int) $file->getMTime());
            if ($used < $olderThan) {
                $count++;
                if (! $dry) {
                    @unlink($file->getPathname());
                }

                continue;
            }
            $kept[] = ['path' => $file->getPathname(), 'used' => $used, 'size' => (int) $file->getSize()];
        }
        $maxBytes = max(0, (int) config('sakila.img_cache_max_mb', 300)) * 1024 * 1024;
        $total = array_sum(array_column($kept, 'size'));
        if ($maxBytes > 0 && $total > $maxBytes) {
            usort($kept, fn (array $a, array $b): int => $a['used'] <=> $b['used']);
            foreach ($kept as $entry) {
                if ($total <= $maxBytes) {
                    break;
                }
                $total -= $entry['size'];
                $count++;
                if (! $dry) {
                    @unlink($entry['path']);
                }
            }
        }

        return $count;
    }
}
