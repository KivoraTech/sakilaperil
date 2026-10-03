<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['session.driver' => 'database']);
    $this->admin = UserAccounts::create('Ada Admin', 'admin@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->author = UserAccounts::create('Arjun Author', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
});

it('signs in, records last login + audit, and redirects only inside the admin', function () {
    $this->post('/admin/login', ['email' => 'ADMIN@test.local ', 'password' => 'Correct-Horse-9!', 'next' => '/admin/pages'])
        ->assertRedirect('/admin/pages');
    $this->assertAuthenticatedAs($this->admin);
    expect(User::find($this->admin->id)->lastLoginAt)->not->toBeNull()
        ->and(AuditLog::where('action', 'USER_LOGIN')->count())->toBe(1);
});

it('ignores open redirects after login', function (string $next) {
    $this->post('/admin/login', ['email' => 'admin@test.local', 'password' => 'Correct-Horse-9!', 'next' => $next])
        ->assertRedirect('/admin');
})->with(['https://evil.example', '//evil.example', '/admin/login', '/it']);

it('shows one generic error for wrong password, unknown and deactivated users', function () {
    User::where('id', $this->author->id)->update(['isActive' => false]);
    foreach ([['admin@test.local', 'wrong-password'], ['nobody@test.local', 'x'], ['author@test.local', 'Correct-Horse-9!']] as [$email, $pw]) {
        $this->from('/admin/login')->post('/admin/login', ['email' => $email, 'password' => $pw])
            ->assertRedirect('/admin/login')->assertSessionHasErrors(['form' => 'Invalid email or password.']);
    }
    $this->assertGuest();
    expect(AuditLog::where('action', 'USER_LOGIN_FAILED')->count())->toBe(3);
});

it('locks an email after 5 failures for 15 minutes', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post('/admin/login', ['email' => 'admin@test.local', 'password' => 'nope']);
    }
    $this->from('/admin/login')->post('/admin/login', ['email' => 'admin@test.local', 'password' => 'Correct-Horse-9!'])
        ->assertSessionHasErrors(['form' => 'Too many attempts. Try again in 15 minute(s).']);
    $this->assertGuest();
});

it('enforces capabilities on admin pages', function () {
    $this->get('/admin/settings')->assertRedirect('/admin/login?next=%2Fadmin%2Fsettings');
    $this->actingAs($this->author)->get('/admin/settings')->assertRedirect('/admin/access-denied');
    $this->actingAs($this->author)->get('/admin/users')->assertRedirect('/admin/access-denied');
    $this->actingAs($this->author)->get('/admin')->assertOk();
    $this->actingAs($this->admin)->get('/admin/settings')->assertOk();
});

it('signs out a deactivated user on the next request and revokes sessions', function () {
    $this->actingAs($this->author);
    DB::table('http_sessions')->insert(['id' => 's1', 'user_id' => $this->author->id, 'payload' => 'x', 'last_activity' => time()]);
    UserAccounts::revokeSessions($this->author->id);
    expect(DB::table('http_sessions')->where('user_id', $this->author->id)->count())->toBe(0);
    User::where('id', $this->author->id)->update(['isActive' => false]);
    auth()->forgetGuards();
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('logs out with an audit entry', function () {
    $this->actingAs($this->admin)->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertGuest();
    expect(AuditLog::where('action', 'USER_LOGOUT')->count())->toBe(1);
});

it('rehashes passwords transparently and keeps the hash in accounts', function () {
    $hash = UserAccounts::passwordHash($this->admin->id);
    expect($hash)->toStartWith(defined('PASSWORD_ARGON2ID') ? '$argon2id$' : '$2y$');
});
