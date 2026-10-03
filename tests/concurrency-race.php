<?php

/**
 * Manual concurrency check for the "last active super admin" rule (not part of Pest: it needs two
 * real connections). Two super admins demote EACH OTHER at the same instant; exactly one must win.
 *   DB_DATABASE=… php tests/concurrency-race.php setup
 *   DB_DATABASE=… php tests/concurrency-race.php demote <actorEmail> <targetEmail> <startAtMicro> &
 */

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Http\Controllers\Admin\UsersController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$cmd = $argv[1] ?? '';
if ($cmd === 'setup') {
    User::query()->where('email', 'like', 'race-%')->delete();
    User::query()->where('role', 'SUPER_ADMIN')->update(['role' => 'ADMIN']);
    UserAccounts::create('Race A', 'race-a@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    UserAccounts::create('Race B', 'race-b@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    echo "ok\n";
    exit;
}
if ($cmd === 'count') {
    echo User::query()->where('role', 'SUPER_ADMIN')->where('isActive', true)->count(), "\n";
    exit;
}
[$_, $_, $actorEmail, $targetEmail, $startAt] = $argv;
$actor = User::query()->where('email', $actorEmail)->firstOrFail();
$target = User::query()->where('email', $targetEmail)->firstOrFail();
while (microtime(true) < (float) $startAt) {
    usleep(200);
}
$request = Request::create('/admin/actions/users/change-role', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'],
    json_encode(['userId' => $target->id, 'role' => 'ADMIN']));
$request->setUserResolver(fn () => $actor);
$response = (new UsersController)->changeRole($request);
echo $actorEmail, ' → ', $response->getContent(), "\n";
