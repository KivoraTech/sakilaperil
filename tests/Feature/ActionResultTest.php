<?php

declare(strict_types=1);

use App\Auth\UserAccounts;
use App\Enums\Role;
use App\Http\Actions\ActionResult;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    Route::middleware(['web', 'admin.auth'])->prefix('admin/actions')->group(function () {
        Route::post('test/ok', fn () => ActionResult::run(fn () => ActionResult::ok(['id' => 'x'], 'Saved')));
        Route::post('test/invalid', fn () => ActionResult::run(fn () => Validator::make([], ['title' => 'required'])->validate() && ActionResult::ok()));
        Route::post('test/boom', fn () => ActionResult::run(fn () => throw new RuntimeException('secret detail')));
        Route::post('test/publish', fn () => ActionResult::run(fn () => ActionResult::ok()))->middleware('capability:pages.publish');
    });
    $this->author = UserAccounts::create('Arjun', 'author@test.local', 'Correct-Horse-9!', Role::AUTHOR);
});

it('returns the ActionResult shape for success, validation, auth and unexpected errors', function () {
    $this->postJson('/admin/actions/test/ok')->assertStatus(401)->assertJson(['ok' => false, 'code' => 'UNAUTHENTICATED']);
    $this->actingAs($this->author);
    $this->postJson('/admin/actions/test/ok')->assertOk()->assertExactJson(['ok' => true, 'data' => ['id' => 'x'], 'message' => 'Saved']);
    $this->postJson('/admin/actions/test/invalid')->assertStatus(422)
        ->assertJson(['ok' => false, 'code' => 'VALIDATION', 'fieldErrors' => ['title' => ['The title field is required.']]]);
    $this->postJson('/admin/actions/test/boom')->assertStatus(500)->assertJsonMissing(['message' => 'secret detail'])
        ->assertJson(['code' => 'ERROR']);
    $this->postJson('/admin/actions/test/publish')->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);
});
