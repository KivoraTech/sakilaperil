<?php

declare(strict_types=1);

use App\Auth\Passwords;
use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['mail.default' => 'array', 'mail.from.address' => 'noreply@sakila.test', 'sakila.site_url' => 'https://sakila.test']);
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->user = UserAccounts::create('Rita', 'rita@sakila.test', 'old password 1234', Role::EDITOR);
});

function sentMessages(): array
{
    return app('mailer')->getSymfonyTransport()->messages()->all();
}

it('answers the same for known and unknown emails and emails only real active users', function () {
    $known = $this->post('/admin/forgot-password', ['email' => 'Rita@sakila.test']);
    $unknown = $this->post('/admin/forgot-password', ['email' => 'nobody@sakila.test']);
    $known->assertRedirect('/admin/login');
    $unknown->assertRedirect('/admin/login');
    expect($known->getSession()->get('status'))->toBe($unknown->getSession()->get('status'))
        ->and(sentMessages())->toHaveCount(1)
        ->and(sentMessages()[0]->getOriginalMessage()->getTo()[0]->getAddress())->toBe('rita@sakila.test');

    $this->user->update(['isActive' => false]);
    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test'])->assertRedirect('/admin/login');
    expect(sentMessages())->toHaveCount(1);
});

it('resets the password once with a valid link, revokes sessions and audits', function () {
    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test']);
    preg_match('#/admin/reset-password/([a-f0-9]{64})#', sentMessages()[0]->getOriginalMessage()->getTextBody(), $m);
    $token = $m[1];
    expect(DB::table('verifications')->where('value', $token)->exists())->toBeFalse(); // stored hashed

    DB::table((string) config('session.table'))->insert(['id' => 's1', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    $this->get('/admin/reset-password/'.$token)->assertOk();
    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'short'])->assertSessionHasErrors('password');
    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'brand new password 99'])->assertRedirect('/admin/login');

    expect(Passwords::verify(UserAccounts::passwordHash($this->user->id), 'brand new password 99'))->toBeTrue()
        ->and(DB::table((string) config('session.table'))->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'USER_PASSWORD_RESET')->where('userId', $this->user->id)->exists())->toBeTrue();

    // Single use.
    $this->post('/admin/reset-password', ['token' => $token, 'password' => 'another password 77'])->assertSessionHasErrors('form');
    expect(Passwords::verify(UserAccounts::passwordHash($this->user->id), 'brand new password 99'))->toBeTrue();
});

it('rejects expired links and rate-limits requests per email', function () {
    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test']);
    preg_match('#/admin/reset-password/([a-f0-9]{64})#', sentMessages()[0]->getOriginalMessage()->getTextBody(), $m);
    DB::table('verifications')->update(['expiresAt' => now('UTC')->subMinute()->format('Y-m-d H:i:s.v')]);
    $this->post('/admin/reset-password', ['token' => $m[1], 'password' => 'brand new password 99'])->assertSessionHasErrors('form');

    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test']);
    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test']);
    $this->post('/admin/forgot-password', ['email' => 'rita@sakila.test'])->assertSessionHasErrors('form');
});
