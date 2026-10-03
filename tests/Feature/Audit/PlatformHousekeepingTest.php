<?php

declare(strict_types=1);

use App\Auth\LoginThrottle;
use App\Auth\Passwords;
use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Install\FirstAdmin;
use App\Models\User;
use App\Support\Cuid;
use Database\Seeders\UsersSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
| Regression tests for the 2026-10 production audit (platform): retention in housekeeping (DB-03,
| SEC-13), the image cache size bound (SEC-01 follow-up), the cache scope per database (CODE-14),
| the users seeder guard (SEC-10), password rules on reset / first admin (QA-17, QA-25).
*/

function auditRow(string $age, ?string $ip = '203.0.113.5'): string
{
    $id = Cuid::make();
    DB::table('audit_logs')->insert([
        'id' => $id, 'action' => 'USER_LOGIN', 'ipAddress' => $ip, 'userAgent' => $ip === null ? null : 'Test/1.0',
        'createdAt' => Carbon::now('UTC')->modify($age)->format('Y-m-d H:i:s.v'),
    ]);

    return $id;
}

it('blanks the IP of old audit entries and deletes very old ones', function () {
    $recent = auditRow('-10 days');
    $old = auditRow('-100 days');
    $ancient = auditRow('-25 months');

    $this->artisan('sakila:housekeeping --dry-run')->assertSuccessful();
    expect(DB::table('audit_logs')->count())->toBe(3)
        ->and(DB::table('audit_logs')->where('id', $old)->value('ipAddress'))->toBe('203.0.113.5');

    $this->artisan('sakila:housekeeping')->assertSuccessful();
    expect(DB::table('audit_logs')->where('id', $recent)->value('ipAddress'))->toBe('203.0.113.5')
        ->and(DB::table('audit_logs')->where('id', $old)->first())->ipAddress->toBeNull()->userAgent->toBeNull()
        ->and(DB::table('audit_logs')->where('id', $ancient)->exists())->toBeFalse();
});

it('keeps audit data forever when the retention is set to 0', function () {
    config(['sakila.retention.audit_ip_days' => 0, 'sakila.retention.audit_months' => 0]);
    $ancient = auditRow('-40 months');
    $this->artisan('sakila:housekeeping')->assertSuccessful();
    expect(DB::table('audit_logs')->where('id', $ancient)->value('ipAddress'))->toBe('203.0.113.5');
});

it('keeps the image variant cache under its size limit, dropping the least recently used files', function () {
    $dir = storage_path('framework/testing/img-cache-'.bin2hex(random_bytes(4)));
    mkdir($dir.'/ab', 0775, true);
    foreach (['oldest' => 5, 'middle' => 3, 'newest' => 1] as $name => $daysAgo) {
        file_put_contents("{$dir}/ab/{$name}.webp", str_repeat('x', 600 * 1024));
        touch("{$dir}/ab/{$name}.webp", time() - $daysAgo * 86400, time() - $daysAgo * 86400);
    }
    file_put_contents("{$dir}/ab/stale.webp", 'x');
    touch("{$dir}/ab/stale.webp", time() - 100 * 86400, time() - 100 * 86400);
    config(['sakila.img_cache_dir' => $dir, 'sakila.img_cache_max_mb' => 1]);

    $this->artisan('sakila:housekeeping')->assertSuccessful();

    expect(is_file("{$dir}/ab/stale.webp"))->toBeFalse()     // unused for 90+ days
        ->and(is_file("{$dir}/ab/oldest.webp"))->toBeFalse()  // over the 1 MB cap: least recently used goes first
        ->and(is_file("{$dir}/ab/middle.webp"))->toBeFalse()
        ->and(is_file("{$dir}/ab/newest.webp"))->toBeTrue();
    File::deleteDirectory($dir);
});

it('gives every database its own cache folder and key prefix', function () {
    $scope = (string) config('cache.scope');
    expect($scope)->toMatch('/^db-[0-9a-f]{12}$/')
        ->and((string) config('cache.stores.file.path'))->toEndWith('/'.$scope)
        ->and((string) config('cache.prefix'))->toContain($scope);

    // A second process pointed at another database must get another scope.
    $other = Process::path(base_path())
        ->env(['DB_DATABASE' => 'another_database_'.bin2hex(random_bytes(3)), 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array'])
        ->run([PHP_BINARY, 'artisan', 'config:show', 'cache.scope', '--no-ansi']);
    preg_match('/db-[0-9a-f]{12}/', $other->output(), $m);
    expect($m[0] ?? null)->not->toBeNull()->not->toBe($scope);
});

it('refuses to seed users in production with the documented default password', function () {
    $this->app->detectEnvironment(fn () => 'production');
    config(['sakila.seed.allow_production' => false]);
    expect(fn () => (new UsersSeeder)->run())->toThrow(RuntimeException::class, 'Refusing to seed users in production');

    config(['sakila.seed.allow_production' => true, 'sakila.seed.admin_password' => UsersSeeder::DEFAULT_PASSWORD]);
    expect(fn () => (new UsersSeeder)->run())->toThrow(RuntimeException::class, 'non-default SEED_ADMIN_PASSWORD');
    expect(User::query()->count())->toBe(0);

    config(['sakila.seed.admin_password' => 'a long and unique passphrase 2026']);
    (new UsersSeeder)->run();
    expect(User::query()->where('role', Role::SUPER_ADMIN->value)->count())->toBe(1)
        ->and(User::query()->count())->toBe(1); // demo editor/author are never created in production
});

it('rejects trivial passwords of acceptable length', function () {
    expect(Passwords::weakness('aaaaaaaaaaaa'))->not->toBeNull()
        ->and(Passwords::weakness('abababababab'))->not->toBeNull()
        ->and(Passwords::weakness('Password1234'))->not->toBeNull()
        ->and(Passwords::weakness('123456789012'))->not->toBeNull()
        ->and(Passwords::weakness('qwertyuiopas'))->not->toBeNull()
        ->and(Passwords::weakness('brand new password 99'))->toBeNull()
        ->and(Passwords::weakness('correct horse battery'))->toBeNull()
        ->and(FirstAdmin::validate('Rita', 'rita@sakila.test', 'aaaaaaaaaaaa'))->toHaveKey('password')
        ->and(FirstAdmin::validate('Rita', 'rita@sakila.test', 'a proper passphrase 7'))->toBe([]);
});

it('password reset: refuses weak or mistyped passwords and lifts the login lock', function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'noreply@sakila.test', 'sakila.site_url' => 'https://sakila.test']);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $user = UserAccounts::create('Rita', 'rita@sakila.test', 'old password 1234', Role::EDITOR);

    // Locked out by five wrong passwords (which anyone can cause for a known address).
    for ($i = 0; $i < LoginThrottle::EMAIL_POLICY['limit']; $i++) {
        LoginThrottle::record('rita@sakila.test', '198.51.100.20', false);
    }
    expect(LoginThrottle::check('rita@sakila.test', null)['allowed'])->toBeFalse();

    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test'])->assertRedirect('/admin/login');
    $mail = app('mailer')->getSymfonyTransport()->messages()->all();
    expect($mail)->toHaveCount(1);
    preg_match('#/admin/reset-password/([a-f0-9]{64})#', $mail[0]->getOriginalMessage()->getTextBody(), $m);
    $token = $m[1];

    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'aaaaaaaaaaaa'])->assertSessionHasErrors('password');
    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'brand new password 99', 'passwordConfirmation' => 'brand new password 98'])
        ->assertSessionHasErrors('passwordConfirmation');
    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'brand new password 99', 'passwordConfirmation' => 'brand new password 99'])
        ->assertRedirect('/admin/login');

    expect(LoginThrottle::check('rita@sakila.test', null)['allowed'])->toBeTrue()
        ->and(Passwords::verify(DB::table('accounts')->where('userId', $user->id)->value('password'), 'brand new password 99'))->toBeTrue();
});
