<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\AccountUserProvider;
use App\Auth\Permissions;
use App\Database\MySqlConnection;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SiteSettings::class);

        // Millisecond-precise DateTime bindings (see App\Database\MySqlConnection).
        Connection::resolverFor('mysql', fn ($pdo, $database, $prefix, $config) => new MySqlConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Built asset URLs are root-relative (/build/assets/…): they must not be derived from the
        // request's Host header, which a client or a mis-keyed cache could forge.
        Vite::createAssetPathsUsing(fn (string $path, ?bool $secure = null): string => '/'.ltrim($path, '/'));

        Auth::provider('accounts', fn (Application $app, array $config) => new AccountUserProvider($app['hash'], $config['model']));

        // Every capability is a Gate: $this->authorize('pages.publish'), @can('settings.manage').
        foreach (Permissions::CAPABILITIES as $capability) {
            Gate::define($capability, fn (User $user): bool => Permissions::can($user->role, $capability));
        }
    }
}
