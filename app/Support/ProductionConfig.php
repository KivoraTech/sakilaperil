<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Throwable;

/**
 * Settings the live site cannot work without. A missing or placeholder value used to fail
 * silently (canonical URLs and email links pointing to http://localhost, uploads going nowhere);
 * now the site answers 503 with the list (App\Http\Middleware\EnsureProductionConfig), and the
 * deploy script refuses to go live (`php artisan sakila:config-check`).
 * Messages name the setting only, never its value.
 */
final class ProductionConfig
{
    /** @return list<string> problems that must be fixed before the site can serve visitors */
    public static function errors(): array
    {
        $errors = [];

        if (trim((string) config('app.key')) === '') {
            $errors[] = 'APP_KEY is empty (generate the settings file with scripts/make-env.php).';
        }
        if (config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false on the live site (true shows error details to visitors).';
        }
        foreach (['SITE_URL' => config('sakila.site_url'), 'APP_URL' => config('app.url')] as $name => $url) {
            $problem = self::urlProblem((string) $url);
            if ($problem !== null) {
                $errors[] = "{$name} {$problem}";
            }
        }
        $db = (array) config('database.connections.'.config('database.default'), []);
        foreach (['DB_DATABASE' => 'database', 'DB_USERNAME' => 'username'] as $name => $key) {
            $value = trim((string) ($db[$key] ?? ''));
            if ($value === '' || self::isPlaceholder($value)) {
                $errors[] = "{$name} is not set.";
            }
        }
        if (self::isPlaceholder((string) ($db['password'] ?? ''))) {
            $errors[] = 'DB_PASSWORD still contains a <placeholder>.';
        }

        $driver = trim((string) config('sakila.storage_driver')) ?: 'local';
        if ($driver === 'local') {
            $dir = trim((string) config('sakila.local_uploads_dir'));
            if ($dir === '' || self::isPlaceholder($dir)) {
                $errors[] = 'LOCAL_UPLOADS_DIR is not set (absolute path of the uploads folder, outside public_html).';
            } elseif (! str_starts_with($dir, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $dir)) {
                $errors[] = 'LOCAL_UPLOADS_DIR must be an absolute path.';
            } elseif (! is_dir($dir) || ! is_writable($dir)) {
                $errors[] = 'LOCAL_UPLOADS_DIR does not exist or is not writable.';
            }
        } elseif ($driver === 's3') {
            foreach (['S3_BUCKET' => 'bucket', 'S3_ACCESS_KEY_ID' => 'access_key_id', 'S3_SECRET_ACCESS_KEY' => 'secret_access_key'] as $name => $key) {
                if (trim((string) config("sakila.s3.{$key}")) === '') {
                    $errors[] = "{$name} is not set (STORAGE_DRIVER=s3).";
                }
            }
        } else {
            $errors[] = 'STORAGE_DRIVER must be "local" or "s3".';
        }

        return $errors;
    }

    /** @return list<string> things that work but should be looked at (never block the site) */
    public static function warnings(): array
    {
        $warnings = [];
        if (str_starts_with((string) config('sakila.site_url'), 'https://') && ! config('session.secure')) {
            $warnings[] = 'SESSION_SECURE_COOKIE should be true on an https site.';
        }
        if (config('mail.default') === 'log' || (config('mail.default') === 'smtp' && trim((string) config('mail.mailers.smtp.host')) === '')) {
            $warnings[] = 'Email is not configured (MAIL_*): notifications, newsletter confirmation and password reset will not be sent.';
        }
        if (filter_var(config('sakila.trust_proxy'), FILTER_VALIDATE_BOOL) && trim((string) config('sakila.trusted_proxies')) === '*') {
            $warnings[] = 'TRUSTED_PROXIES=* trusts forwarded headers from anyone who can reach the server; use "cloudflare" or a list of proxy addresses.';
        }
        if (trim((string) config('sakila.install_token')) !== '') {
            try {
                if (User::query()->where('role', Role::SUPER_ADMIN->value)->where('isActive', true)->exists()) {
                    $warnings[] = 'INSTALL_TOKEN is still in .env although an administrator exists: delete that line.';
                }
            } catch (Throwable) {
                // database not reachable: reported elsewhere
            }
        }

        return $warnings;
    }

    private static function urlProblem(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || self::isPlaceholder($url)) {
            return 'is not set (the public address, e.g. https://www.example.org).';
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return 'is not a valid address.';
        }
        $loopback = in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        if (! str_starts_with($url, 'https://') && ! $loopback) {
            return 'must start with https://';
        }
        if (str_ends_with($url, '/')) {
            return 'must not end with a slash.';
        }

        return null;
    }

    private static function isPlaceholder(string $value): bool
    {
        return (bool) preg_match('/<[^<>]+>/', $value);
    }
}
