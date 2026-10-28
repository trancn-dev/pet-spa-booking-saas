<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'tenant'])->prefix('app/{tenant:slug}')
        ->get('__staff', fn () => Staff::pluck('display_name'));

    $this->a = Tenant::factory()->create(['slug' => 'a']);
    $this->b = Tenant::factory()->create(['slug' => 'b']);
    inTenant($this->a, fn () => Staff::factory()->create(['display_name' => 'staff-a']));
    inTenant($this->b, fn () => Staff::factory()->create(['display_name' => 'staff-b']));
});

it('serves only the data of the tenant in the URL slug', function () {
    $this->get('/app/a/__staff')->assertOk()->assertExactJson(['staff-a']);
    $this->get('/app/b/__staff')->assertOk()->assertExactJson(['staff-b']);
});

it('returns 404 for an unknown or cancelled tenant', function () {
    $this->get('/app/nope/__staff')->assertNotFound();

    $this->b->update(['status' => 'cancelled']);
    $this->get('/app/b/__staff')->assertNotFound();
});

it('serves a suspended tenant read-only: its own data on GET, 423 on writes (task 4.5)', function () {
    Route::middleware(['web', 'tenant'])->prefix('app/{tenant:slug}')
        ->post('__staff', fn () => 'written')->withoutMiddleware(ValidateCsrfToken::class);

    $this->b->update(['status' => 'suspended']);

    $this->get('/app/b/__staff')->assertOk()->assertExactJson(['staff-b']);
    $this->postJson('/app/b/__staff')->assertStatus(423);
    $this->postJson('/app/a/__staff')->assertOk();
});

it('serves trial and past-due shops (still usable, db-schema 2.1)', function () {
    $this->b->update(['status' => 'trial']);
    $this->get('/app/b/__staff')->assertOk();

    $this->b->update(['status' => 'past_due']);
    $this->get('/app/b/__staff')->assertOk();
});

it('returns 404 when a logged-in user is not a member of the tenant', function () {
    $user = User::factory()->create();
    inTenant($this->a, fn () => Staff::factory()->create(['user_id' => $user->id]));

    $this->actingAs($user)->get('/app/a/__staff')->assertOk();
    $this->actingAs($user)->get('/app/b/__staff')->assertNotFound();
});

it('ignores members whose staff row is disabled or deleted', function () {
    $user = User::factory()->create();
    $staff = inTenant($this->a, fn () => Staff::factory()->create(['user_id' => $user->id, 'status' => 'disabled']));

    // Disabled with no other shop: no data, signed out, back to login (task 4.3).
    $this->actingAs($user)->get('/app/a/__staff')->assertRedirect('/login');
    $this->assertGuest('web');

    $staff->update(['status' => 'active']);
    $staff->delete();
    $this->actingAs($user)->get('/app/a/__staff')->assertNotFound();
});

it('fills the shop slug into generated URLs, with no stray query string', function () {
    Route::middleware(['web', 'tenant'])->prefix('app/{tenant:slug}')
        ->get('__urls', fn () => [route('app.dashboard'), route('app.settings.roles.update', ['role' => 5])]);

    $this->get('/app/a/__urls')->assertExactJson([url('/app/a/dashboard'), url('/app/a/settings/roles/5')]);
});

it('remembers the last opened tenant', function () {
    $user = User::factory()->create();
    inTenant($this->a, fn () => Staff::factory()->create(['user_id' => $user->id]));
    inTenant($this->b, fn () => Staff::factory()->create(['user_id' => $user->id]));

    $this->actingAs($user)->get('/app/b/__staff')->assertOk();

    expect($user->fresh()->last_tenant_id)->toBe($this->b->id);
});

it('does not leak tenant context between requests', function () {
    $this->get('/app/a/__staff')->assertOk();
    $this->get('/app/nope/__staff')->assertNotFound();
    $this->get('/app/b/__staff')->assertExactJson(['staff-b']);
});
