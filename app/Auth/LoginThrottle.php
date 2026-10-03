<?php

declare(strict_types=1);

namespace App\Auth;

use App\Models\LoginThrottle as LoginThrottleRow;
use Carbon\CarbonImmutable;

/**
 * Port of sakila/lib/auth/throttle{,-policy}.ts. Fixed window + lockout, persisted in
 * `login_throttles` with hashed keys (no raw emails/IPs):
 *   per email: 5 failed attempts / 15 min → locked 15 min
 *   per IP:   20 failed attempts / 15 min → locked 15 min
 */
final class LoginThrottle
{
    /** @var array{limit:int, windowMs:int, lockMs:int} */
    public const EMAIL_POLICY = ['limit' => 5, 'windowMs' => 15 * 60_000, 'lockMs' => 15 * 60_000];

    /** @var array{limit:int, windowMs:int, lockMs:int} */
    public const IP_POLICY = ['limit' => 20, 'windowMs' => 15 * 60_000, 'lockMs' => 15 * 60_000];

    /** @return array{allowed: bool, retryAfter: int} */
    public static function check(string $email, ?string $ip): array
    {
        $now = CarbonImmutable::now('UTC');
        $states = [self::load(self::key('email', $email)), $ip !== null ? self::load(self::key('ip', $ip)) : null];
        foreach ($states as $state) {
            if (self::isLocked($state, $now)) {
                return ['allowed' => false, 'retryAfter' => self::retryAfterSeconds($state, $now)];
            }
        }

        return ['allowed' => true, 'retryAfter' => 0];
    }

    /** Failures count against email and IP; a success clears the email counter. */
    public static function record(string $email, ?string $ip, bool $success): void
    {
        $now = CarbonImmutable::now('UTC');
        if ($success) {
            LoginThrottleRow::query()->where('key', self::key('email', $email))->delete();

            return;
        }
        self::bump(self::key('email', $email), self::EMAIL_POLICY, $now);
        if ($ip !== null) {
            self::bump(self::key('ip', $ip), self::IP_POLICY, $now);
        }
    }

    /**
     * @param  array{count:int, windowStart:CarbonImmutable, lockedUntil:?CarbonImmutable}|null  $prev
     * @param  array{limit:int, windowMs:int, lockMs:int}  $policy
     * @return array{count:int, windowStart:CarbonImmutable, lockedUntil:?CarbonImmutable}
     */
    public static function registerEvent(?array $prev, CarbonImmutable $now, array $policy): array
    {
        $expired = $prev === null || self::ms($now) - self::ms($prev['windowStart']) >= $policy['windowMs'];
        $base = $expired ? ['count' => 0, 'windowStart' => $now, 'lockedUntil' => null] : $prev;
        $count = $base['count'] + 1;
        $lockedUntil = $count >= $policy['limit'] ? $now->addMilliseconds($policy['lockMs']) : $base['lockedUntil'];

        return ['count' => $count, 'windowStart' => $base['windowStart'], 'lockedUntil' => $lockedUntil];
    }

    /** @param array{count:int, windowStart:CarbonImmutable, lockedUntil:?CarbonImmutable}|null $state */
    public static function isLocked(?array $state, CarbonImmutable $now): bool
    {
        return $state !== null && $state['lockedUntil'] !== null && $state['lockedUntil']->greaterThan($now);
    }

    /** @param array{count:int, windowStart:CarbonImmutable, lockedUntil:?CarbonImmutable}|null $state */
    public static function retryAfterSeconds(?array $state, CarbonImmutable $now): int
    {
        if ($state === null || $state['lockedUntil'] === null) {
            return 0;
        }

        return max(0, (int) ceil((self::ms($state['lockedUntil']) - self::ms($now)) / 1000));
    }

    public static function key(string $kind, string $value): string
    {
        return 'login:'.$kind.':'.substr(hash('sha256', mb_strtolower(trim($value))), 0, 32);
    }

    /** @return array{count:int, windowStart:CarbonImmutable, lockedUntil:?CarbonImmutable}|null */
    private static function load(string $key): ?array
    {
        $row = LoginThrottleRow::query()->find($key);
        if ($row === null) {
            return null;
        }

        return [
            'count' => (int) $row->count,
            'windowStart' => CarbonImmutable::parse($row->windowStart, 'UTC'),
            'lockedUntil' => $row->lockedUntil !== null ? CarbonImmutable::parse($row->lockedUntil, 'UTC') : null,
        ];
    }

    /** @param array{limit:int, windowMs:int, lockMs:int} $policy */
    private static function bump(string $key, array $policy, CarbonImmutable $now): void
    {
        $next = self::registerEvent(self::load($key), $now, $policy);
        LoginThrottleRow::query()->updateOrCreate(['key' => $key], $next);
    }

    private static function ms(CarbonImmutable $t): int
    {
        return (int) $t->getPreciseTimestamp(3);
    }
}
