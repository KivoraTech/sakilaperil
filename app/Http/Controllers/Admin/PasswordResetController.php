<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Audit\Audit;
use App\Audit\AuditAction;
use App\Auth\LoginThrottle;
use App\Auth\Passwords;
use App\Auth\UserAccounts;
use App\Engagement\Mail\Mailer;
use App\Http\Controllers\Controller;
use App\Install\FirstAdmin;
use App\Models\User;
use App\Support\Cuid;
use App\Support\RequestInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Self-service password reset for CMS users (email via the cPanel mailbox).
 * - No account enumeration: the same answer for unknown, inactive and active emails.
 * - Single-use tokens, stored hashed (SHA-256) in `verifications`, valid for 60 minutes; a new
 *   request replaces the previous token.
 * - Rate-limited per email and per IP. A successful reset signs the user out everywhere (all
 *   sessions revoked) and is audited.
 */
final class PasswordResetController extends Controller
{
    private const TTL_MINUTES = 60;

    private const SENT = 'If this email belongs to an active account, we have sent a link to reset the password. It expires in 60 minutes.';

    public function request(): Response
    {
        return Inertia::render('auth/forgot-password')->withViewData(['title' => 'Reset password']);
    }

    public function send(Request $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->input('email', '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 200) {
            return back()->withErrors(['email' => 'Enter a valid email address.']);
        }
        $ipKey = 'pwreset:ip:'.RequestInfo::clientIp();
        $emailKey = 'pwreset:email:'.sha1($email);
        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($emailKey, 3)) {
            return back()->withErrors(['form' => 'Too many requests. Try again in 15 minutes.']);
        }
        RateLimiter::hit($ipKey, 900);
        RateLimiter::hit($emailKey, 900);

        // The token is created and the email sent AFTER the response, so the answer takes the same
        // time whether or not the address belongs to an account (SMTP can take seconds).
        defer(function () use ($email): void {
            $user = User::query()->where('email', $email)->where('isActive', true)->first();
            if ($user !== null) {
                $this->issue($user);
            }
        });

        return redirect('/admin/login')->with('status', self::SENT);
    }

    public function edit(string $token): Response
    {
        $valid = $this->find($token) !== null;

        return Inertia::render('auth/reset-password', ['token' => $token, 'valid' => $valid])->withViewData(['title' => 'Choose a new password']);
    }

    public function update(Request $request): RedirectResponse
    {
        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $row = $this->find($token);
        if ($row === null) {
            return back()->withErrors(['form' => 'This link has expired or was already used. Request a new one.']);
        }
        if (mb_strlen($password) < FirstAdmin::PASSWORD_MIN_LENGTH || mb_strlen($password) > FirstAdmin::PASSWORD_MAX_LENGTH) {
            return back()->withErrors(['password' => 'Use '.FirstAdmin::PASSWORD_MIN_LENGTH.'–'.FirstAdmin::PASSWORD_MAX_LENGTH.' characters.']);
        }
        if (($weak = Passwords::weakness($password)) !== null) {
            return back()->withErrors(['password' => $weak]);
        }
        // The form sends the password twice; a mismatch is a typo the user could not otherwise notice.
        if ($request->has('passwordConfirmation') && ! hash_equals($password, (string) $request->input('passwordConfirmation'))) {
            return back()->withErrors(['passwordConfirmation' => 'The two passwords are not the same.']);
        }
        $userId = substr((string) $row->identifier, strlen('admin-password-reset:'));
        $user = User::query()->whereKey($userId)->where('isActive', true)->first();
        if ($user === null) {
            return back()->withErrors(['form' => 'This link has expired or was already used. Request a new one.']);
        }
        DB::table('verifications')->where('identifier', $row->identifier)->delete();
        UserAccounts::setPassword($user->id, $password); // also revokes every session
        // Proving control of the mailbox also lifts a login lock on this email (5 wrong passwords
        // lock it for 15 minutes — which anyone can trigger for a known address).
        LoginThrottle::record($user->email, null, true);
        Audit::log(AuditAction::USER_PASSWORD_RESET, $user->id, 'User', $user->id, ['via' => 'email-link']);

        return redirect('/admin/login')->with('status', 'Your password has been changed. Sign in with the new password.');
    }

    private function issue(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $now = Carbon::now('UTC');
        $identifier = 'admin-password-reset:'.$user->id;
        DB::table('verifications')->where('identifier', $identifier)->delete();
        DB::table('verifications')->insert([
            'id' => Cuid::make(),
            'identifier' => $identifier,
            'value' => hash('sha256', $token),
            'expiresAt' => $now->copy()->addMinutes(self::TTL_MINUTES)->format('Y-m-d H:i:s.v'),
            'createdAt' => $now->format('Y-m-d H:i:s.v'),
            'updatedAt' => $now->format('Y-m-d H:i:s.v'),
        ]);
        $link = rtrim((string) config('sakila.site_url'), '/').'/admin/reset-password/'.$token;
        try {
            $sent = Mailer::send(
                $user->email,
                'Reset your Sakila CMS password',
                "Hello {$user->name},\n\nSomeone (hopefully you) asked to reset the password of your Sakila CMS account.\n"
                ."Open this link within 60 minutes to choose a new password:\n\n{$link}\n\n"
                ."If you didn't ask for this, you can ignore this email — your password stays the same.\n",
            );
            if (! $sent) {
                logger()->warning('[password-reset] requested but email is not configured (MAIL_* in .env)', ['user' => $user->id]);
            }
        } catch (Throwable $e) {
            logger()->error('[password-reset] email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function find(string $token): ?object
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $row = DB::table('verifications')
            ->where('value', hash('sha256', $token))
            ->where('identifier', 'like', 'admin-password-reset:%')
            ->first();
        if ($row === null || Carbon::parse((string) $row->expiresAt, 'UTC')->isPast()) {
            return null;
        }

        return $row;
    }
}
