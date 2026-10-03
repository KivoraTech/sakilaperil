<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    User::query()->delete();
});

it('hides /install unless a token is configured and no super admin exists', function () {
    config(['sakila.install_token' => '']);
    $this->get('/install')->assertNotFound();
    config(['sakila.install_token' => 'short']);
    $this->get('/install')->assertNotFound();
    config(['sakila.install_token' => str_repeat('k', 24)]);
    $this->get('/install')->assertOk()->assertSee('Create the first administrator');
});

it('creates exactly one super admin, then disables itself', function () {
    config(['sakila.install_token' => str_repeat('k', 24)]);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $body = ['token' => str_repeat('k', 24), 'name' => 'Ada', 'email' => 'ADA@example.org', 'password' => 'correct horse battery'];

    $this->post('/install', [...$body, 'token' => 'wrong-token-wrong-token'])->assertStatus(422)->assertSee('install token is not correct');
    $this->post('/install', [...$body, 'password' => 'short'])->assertStatus(422);
    expect(User::query()->count())->toBe(0);

    $this->post('/install', $body)->assertRedirect('/admin/login');
    $admin = User::query()->sole();
    expect($admin->email)->toBe('ada@example.org')->and($admin->role)->toBe(Role::SUPER_ADMIN)
        ->and(AuditLog::query()->where('action', 'USER_CREATED')->where('userId', $admin->id)->exists())->toBeTrue();

    $this->get('/install')->assertNotFound();
    $this->post('/install', [...$body, 'email' => 'eve@example.org'])->assertNotFound();
    expect(User::query()->count())->toBe(1);
});

it('rate-limits wrong install tokens', function () {
    config(['sakila.install_token' => str_repeat('k', 24)]);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    foreach (range(1, 5) as $_) {
        $this->post('/install', ['token' => 'nope', 'name' => 'x', 'email' => 'x@example.org', 'password' => str_repeat('p', 12)])->assertStatus(422);
    }
    $this->post('/install', ['token' => str_repeat('k', 24), 'name' => 'x', 'email' => 'x@example.org', 'password' => str_repeat('p', 12)])->assertStatus(429);
});

it('creates the first admin from the command line once', function () {
    $this->artisan('sakila:create-admin', ['email' => 'root@example.org', '--name' => 'Root'])
        ->expectsQuestion('Password (12+ characters, input hidden)', 'correct horse battery')
        ->assertSuccessful();
    expect(User::query()->where('role', Role::SUPER_ADMIN->value)->count())->toBe(1);
    $this->artisan('sakila:create-admin', ['email' => 'two@example.org'])->assertFailed();
});
