<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Http\Controllers\Admin\UsersController;
use App\Models\AuditLog;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\User;
use App\Queries\Admin\DashboardQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/* Menus, settings, users, audit and the admin screens. */

beforeEach(function () {
    $this->withoutVite();
    $this->super = UserAccounts::create('Sita Super', 'super@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->admin = UserAccounts::create('Asha Admin', 'admin@test.local', 'Correct-Horse-9!', Role::ADMIN);
    $this->editor = UserAccounts::create('Eli Editor', 'editor@test.local', 'Correct-Horse-9!', Role::EDITOR);
    $this->author = UserAccounts::create('Arjun Author', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
    $this->page = Page::query()->create(['status' => 'DRAFT']);
    PageTranslation::query()->create(['pageId' => $this->page->id, 'locale' => 'it', 'title' => 'Chi siamo', 'slug' => 'chi-siamo']);
});

// ───────────── menus ─────────────

it('replaces a menu with nested items, audits it and expires the navigation cache', function () {
    $this->actingAs($this->editor);
    $before = Cache::get('tagv:navigation');
    $this->postJson('/admin/actions/navigation/save', ['key' => 'header', 'items' => [
        ['label' => ['it' => 'Chi siamo', 'en' => 'About'], 'linkType' => 'PAGE', 'pageId' => $this->page->id, 'depth' => 0],
        ['label' => ['it' => 'Progetti'], 'linkType' => 'ROUTE', 'routeKey' => 'projects', 'depth' => 1],
        ['label' => ['it' => 'Esterno'], 'linkType' => 'EXTERNAL', 'href' => 'https://example.org', 'openInNewTab' => true, 'depth' => 0],
    ]])->assertOk()->assertJson(['data' => ['count' => 3], 'message' => 'Menu saved — it is live on the website']);

    $nav = Navigation::query()->where('key', 'header')->firstOrFail();
    $items = NavigationItem::query()->where('navigationId', $nav->id)->orderBy('createdAt')->get();
    expect($items)->toHaveCount(3)
        ->and($items->firstWhere('routeKey', 'projects')->parentId)->toBe($items->firstWhere('pageId', $this->page->id)->id)
        ->and($items->firstWhere('pageId', $this->page->id)->label)->toBe(['it' => 'Chi siamo', 'en' => 'About'])
        ->and(Cache::get('tagv:navigation'))->not->toBe($before)
        ->and(AuditLog::query()->where('action', 'NAVIGATION_UPDATED')->where('entityId', 'header')->exists())->toBeTrue();

    // Footer menus are flat; links must be safe; pages must exist; authors may not.
    $this->postJson('/admin/actions/navigation/save', ['key' => 'footer-help', 'items' => [
        ['label' => ['it' => 'A'], 'linkType' => 'ROUTE', 'routeKey' => 'news'],
        ['label' => ['it' => 'B'], 'linkType' => 'ROUTE', 'routeKey' => 'news', 'depth' => 1],
    ]])->assertStatus(422)->assertJsonPath('fieldErrors.items.0', 'This menu does not support nesting');
    $this->postJson('/admin/actions/navigation/save', ['key' => 'footer-help', 'items' => [
        ['label' => ['it' => 'A'], 'linkType' => 'INTERNAL', 'href' => 'javascript:alert(1)'],
    ]])->assertStatus(422);
    $this->postJson('/admin/actions/navigation/save', ['key' => 'header', 'items' => [
        ['label' => ['it' => 'A'], 'linkType' => 'PAGE', 'pageId' => 'missing'],
    ]])->assertStatus(422)->assertJson(['message' => 'One of the linked pages no longer exists. Reload and try again.']);
    $this->actingAs($this->author);
    $this->postJson('/admin/actions/navigation/save', ['key' => 'header', 'items' => []])->assertStatus(403);
});

// ───────────── settings ─────────────

it('saves one settings section, checks referenced images and expires the settings cache', function () {
    DB::table('site_settings')->insert([
        'id' => 'default', 'orgName' => 'Sakila per il Nepal', 'organization' => '{}', 'contact' => '{}', 'socialLinks' => '[]',
        'bankDetails' => '{}', 'nepalPayments' => '{}', 'donation' => '{}', 'impactStats' => '[]', 'seo' => '{}', 'appearance' => '{}',
        'analytics' => '{}', 'formMessages' => '{}', 'features' => '{}', 'updatedAt' => now()->format('Y-m-d H:i:s.v'),
    ]);
    $this->actingAs($this->admin);
    $before = Cache::get('tagv:settings');
    $this->postJson('/admin/actions/settings/update', ['section' => 'appearance', 'data' => [
        'appearance' => ['headerStyle' => 'solid', 'enableMotion' => false, 'accent' => 'sindoor', 'logoImageId' => ''],
    ]])->assertOk()->assertJson(['message' => 'Settings saved — live on the website']);
    $row = DB::table('site_settings')->first();
    expect(json_decode($row->appearance, true))->toMatchArray(['headerStyle' => 'solid', 'enableMotion' => false])
        ->and(json_decode($row->appearance, true))->not->toHaveKey('logoImageId')
        ->and($row->updatedById)->toBe($this->admin->id)
        ->and(Cache::get('tagv:settings'))->not->toBe($before)
        ->and(AuditLog::query()->where('action', 'SETTINGS_UPDATED')->first()->metadata)->toBe(['section' => 'appearance', 'fields' => ['appearance']]);

    $this->postJson('/admin/actions/settings/update', ['section' => 'appearance', 'data' => [
        'appearance' => ['headerStyle' => 'solid', 'logoImageId' => 'missing-image'],
    ]])->assertStatus(422)->assertJson(['message' => 'One of the selected images no longer exists.']);
    $this->postJson('/admin/actions/settings/update', ['section' => 'nope', 'data' => []])->assertStatus(422);

    $this->actingAs($this->editor);
    $this->postJson('/admin/actions/settings/update', ['section' => 'appearance', 'data' => []])->assertStatus(403);
});

// ───────────── users ─────────────

it('creates users with a one-time temporary password and enforces role rules', function () {
    $this->actingAs($this->admin);
    $r = $this->postJson('/admin/actions/users/create', ['name' => 'Nuovo', 'email' => 'NEW@Test.local', 'role' => 'EDITOR', 'password' => ''])
        ->assertOk()->assertJson(['message' => 'User created']);
    expect($r->json('data.temporaryPassword'))->toMatch('/^[A-Za-z2-9]{5}(-[A-Za-z2-9]{5}){3}$/')
        ->and(User::query()->find($r->json('data.id'))->email)->toBe('new@test.local');
    $this->postJson('/admin/actions/users/create', ['name' => 'X', 'email' => 'new@test.local', 'role' => 'EDITOR'])->assertStatus(409);
    $this->postJson('/admin/actions/users/create', ['name' => 'X', 'email' => 'x@test.local', 'role' => 'SUPER_ADMIN'])
        ->assertStatus(403)->assertJson(['message' => 'You cannot create users with this role.']);
    $this->postJson('/admin/actions/users/create', ['name' => 'X', 'email' => 'x@test.local', 'role' => 'EDITOR', 'password' => 'short'])
        ->assertStatus(422)->assertJsonPath('fieldErrors.password.0', 'Use at least 12 characters');

    // Admins cannot manage super admins; nobody changes their own role.
    $this->postJson('/admin/actions/users/change-role', ['userId' => $this->super->id, 'role' => 'EDITOR'])->assertStatus(403);
    $this->postJson('/admin/actions/users/change-role', ['userId' => $this->admin->id, 'role' => 'EDITOR'])
        ->assertStatus(403)->assertJson(['message' => 'You cannot change your own role. Ask another admin.']);
    $this->postJson('/admin/actions/users/change-role', ['userId' => $this->editor->id, 'role' => 'AUTHOR'])->assertOk();
    expect(User::query()->find($this->editor->id)->role)->toBe(Role::AUTHOR)
        ->and(AuditLog::query()->where('action', 'USER_ROLE_CHANGED')->exists())->toBeTrue();

    $this->actingAs($this->author);
    $this->postJson('/admin/actions/users/rename', ['userId' => $this->author->id, 'name' => 'Me'])->assertStatus(403);
});

it('never demotes or deactivates the last active super admin', function () {
    $other = UserAccounts::create('Second Super', 'super2@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->actingAs($this->super);
    $this->postJson('/admin/actions/users/set-active', ['userId' => $other->id, 'active' => false])->assertOk();
    expect(UsersController::checkSetActive($this->super, $this->super->refresh(), false, 1))
        ->toBe(['FORBIDDEN', 'You cannot deactivate your own account.']);
    $third = UserAccounts::create('Third Super', 'super3@test.local', 'Correct-Horse-9!', Role::SUPER_ADMIN);
    $this->actingAs($third);
    // super + third active → demoting super leaves third: allowed; then third cannot be demoted by itself.
    $this->postJson('/admin/actions/users/change-role', ['userId' => $this->super->id, 'role' => 'ADMIN'])->assertOk();
    $this->actingAs($this->super->refresh());
    $this->postJson('/admin/actions/users/change-role', ['userId' => $third->id, 'role' => 'ADMIN'])->assertStatus(403);
    expect(UsersController::checkRoleChange($this->admin, $third, Role::ADMIN, 1))->toBe(['FORBIDDEN', 'You cannot manage this user.'])
        ->and(UsersController::checkRoleChange($other, $third, Role::ADMIN, 1))
        ->toBe(['CONFLICT', 'This is the last super admin. Make someone else super admin first.']);
});

it('resets passwords and signs users out everywhere', function () {
    DB::table((string) config('session.table'))->insert(['id' => 'sess-1', 'user_id' => $this->editor->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->admin);
    $this->postJson('/admin/actions/users/revoke-sessions', ['userId' => $this->editor->id])->assertOk()->assertJson(['data' => ['count' => 1]]);
    $r = $this->postJson('/admin/actions/users/reset-password', ['userId' => $this->editor->id])->assertOk();
    expect($r->json('data.temporaryPassword'))->toBeString();
    $this->postJson('/admin/actions/users/rename', ['userId' => $this->editor->id, 'name' => '  Eli  '])->assertOk();
    expect(User::query()->find($this->editor->id)->name)->toBe('Eli');
});

// ───────────── screens ─────────────

it('renders the admin screens with their props, gated by capability', function () {
    $this->actingAs($this->admin);
    $this->get('/admin')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/dashboard')
        ->where('data.user.name', 'Asha Admin')->has('data.checklist')->has('data.activity'));
    $this->get('/admin/pages?status=bogus&page=-3')->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/pages/index')
        ->where('params.status', 'all')->where('params.page', 1)->where('total', 1)->where('canCreate', true)->where('items.0.title', 'Chi siamo'));
    $this->get('/admin/pages?new=1')->assertInertia(fn (Assert $p) => $p->where('openCreate', true));
    $this->get("/admin/pages/{$this->page->id}?locale=en")->assertOk()->assertInertia(fn (Assert $p) => $p->component('admin/pages/edit')
        ->where('locale', 'en')->where('data.draft', null)->where('references', null));
    $this->get("/admin/pages/{$this->page->id}")->assertInertia(fn (Assert $p) => $p->where('data.draft.slug', 'chi-siamo')->has('references.images')->has('linkSuggestions'));
    $this->get('/admin/pages/missing')->assertNotFound();
    $this->get('/admin/navigation?menu=footer-help')->assertInertia(fn (Assert $p) => $p->component('admin/navigation/index')->where('menu', 'footer-help')->has('data.menus', 4));
    $this->get('/admin/settings?tab=seo')->assertInertia(fn (Assert $p) => $p->component('admin/settings/index')->where('tab', 'seo')->has('settings.seo'));
    $this->get('/admin/users')->assertInertia(fn (Assert $p) => $p->component('admin/users/index')->has('users', 4)->where('activeSuperAdmins', 1));
    $this->get('/admin/audit?action=NOPE')->assertInertia(fn (Assert $p) => $p->component('admin/audit/index')->missing('params.action'));

    $this->actingAs($this->author);
    $this->get('/admin/users')->assertRedirect('/admin/access-denied');
    $this->get('/admin/settings')->assertRedirect('/admin/access-denied');
    $this->get('/admin/pages')->assertOk()->assertInertia(fn (Assert $p) => $p->where('canCreate', false));
});

it('describes activity in plain English and builds the getting-started checklist from real data', function () {
    expect(DashboardQuery::describeActivity('PAGE_PUBLISHED', 'Page'))->toBe('published a page')
        ->and(DashboardQuery::describeActivity('USER_LOGIN', 'User'))->toBe('signed in')
        ->and(DashboardQuery::describeActivity('SUBMISSIONS_EXPORTED', null))->toBe('exported form submissions')
        ->and(DashboardQuery::describeActivity('SOMETHING_NEW', 'Widget'))->toBe('something new widget');
    $setup = now()->subDay();
    $items = DashboardQuery::buildChecklist([
        'setupAt' => $setup, 'canSettings' => true, 'canContent' => true, 'canPages' => true, 'todoCount' => 0,
        'demoContent' => true, 'lastNewsCreatedAt' => $setup->copy()->addMinutes(5), 'lastMediaCreatedAt' => now(),
        'homePublishedAt' => null, 'homeId' => 'home-id',
    ]);
    expect(collect($items)->pluck('done', 'id')->all())->toBe(['details' => true, 'home' => false, 'photos' => true, 'news' => false, 'demo' => false])
        ->and(collect($items)->firstWhere('id', 'home')['href'])->toBe('/admin/pages/home-id');
});
