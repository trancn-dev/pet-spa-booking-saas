<?php

declare(strict_types=1);

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantMembership;

/*
| Task 4.3: an account only works while it is active staff of a shop that is not cancelled.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->a = Tenant::factory()->create(['slug' => 'a', 'name' => 'A']);
    $this->b = Tenant::factory()->create(['slug' => 'b', 'name' => 'B']);
    $this->user = User::factory()->create(['email' => 'nv@paw.test']);
    $this->staffA = inTenant($this->a, fn () => Staff::factory()->create(['user_id' => $this->user->id]));
    $this->login = fn () => $this->post('/login', ['email' => 'nv@paw.test', 'password' => 'password']);
});

it('refuses to sign in without an active shop', function (Closure $setup) {
    $setup($this);

    ($this->login)()->assertSessionHasErrors(['email' => LoginRequest::NO_ACTIVE_SHOP]);
    $this->assertGuest('web');
})->with([
    'never joined a shop' => [fn ($t) => $t->staffA->forceDelete()],
    'disabled at the only shop' => [fn ($t) => $t->staffA->update(['status' => 'disabled'])],
    'the only shop is cancelled' => [fn ($t) => $t->a->update(['status' => 'cancelled'])],
]);

it('signs in to the shop still active and hides the other one', function () {
    $this->staffA->update(['status' => 'disabled']);
    inTenant($this->b, fn () => Staff::factory()->create(['user_id' => $this->user->id]));

    ($this->login)()->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($this->user, 'web');

    $this->get('/dashboard')->assertRedirect('/app/b/dashboard');
    expect(app(TenantMembership::class)->tenantsFor($this->user)->pluck('slug')->all())->toBe(['b']);
});

it('moves a user disabled mid-session to their other shop, or signs them out', function () {
    inTenant($this->b, fn () => Staff::factory()->create(['user_id' => $this->user->id]));
    $this->actingAs($this->user)->get('/app/a/dashboard')->assertOk();

    $this->staffA->update(['status' => 'disabled']);
    $this->get('/app/a/dashboard')->assertRedirect('/app/b/dashboard');
    $this->assertAuthenticatedAs($this->user, 'web');

    inTenant($this->b, fn () => Staff::query()->where('user_id', $this->user->id)->update(['status' => 'disabled']));
    $this->get('/app/b/dashboard')->assertRedirect('/login');
    $this->assertGuest('web');
});
