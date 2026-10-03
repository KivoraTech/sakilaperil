<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('deletes only expired or stale housekeeping rows', function () {
    $ms = fn (Carbon $c): string => $c->copy()->utc()->format('Y-m-d H:i:s.v');
    $now = Carbon::now('UTC');
    DB::table('form_rate_limits')->insert([
        ['key' => 'old', 'count' => 1, 'windowStart' => $ms($now->copy()->subDays(2))],
        ['key' => 'new', 'count' => 1, 'windowStart' => $ms($now)],
    ]);
    DB::table('login_throttles')->insert([
        ['key' => 'stale', 'count' => 1, 'windowStart' => $ms($now->copy()->subDays(2)), 'lockedUntil' => null, 'updatedAt' => $ms($now->copy()->subDays(2))],
        ['key' => 'locked', 'count' => 9, 'windowStart' => $ms($now->copy()->subDays(2)), 'lockedUntil' => $ms($now->copy()->addHour()), 'updatedAt' => $ms($now->copy()->subDays(2))],
        ['key' => 'fresh', 'count' => 1, 'windowStart' => $ms($now), 'lockedUntil' => null, 'updatedAt' => $ms($now)],
    ]);
    DB::table('rate_limits')->insert([
        ['id' => 'r1', 'key' => 'old', 'count' => 1, 'lastRequest' => $now->copy()->subDays(2)->getTimestampMs()],
        ['id' => 'r2', 'key' => 'new', 'count' => 1, 'lastRequest' => $now->getTimestampMs()],
    ]);
    DB::table((string) config('session.table'))->insert([
        ['id' => 'expired', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => $now->getTimestamp() - (int) config('session.lifetime') * 60 - 10],
        ['id' => 'active', 'user_id' => null, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => $now->getTimestamp()],
    ]);

    $this->artisan('sakila:housekeeping', ['--dry-run' => true])->assertSuccessful();
    expect(DB::table('form_rate_limits')->count())->toBe(2);

    $this->artisan('sakila:housekeeping')->assertSuccessful();
    expect(DB::table('form_rate_limits')->pluck('key')->all())->toBe(['new'])
        ->and(DB::table('login_throttles')->orderBy('key')->pluck('key')->all())->toBe(['fresh', 'locked'])
        ->and(DB::table('rate_limits')->pluck('key')->all())->toBe(['new'])
        ->and(DB::table((string) config('session.table'))->pluck('id')->all())->toBe(['active']);
});
