<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;

/*
| MANDATORY: a user must never reach a tenant they do not belong to.
| Never delete or weaken these; extend them when adding auth features.
*/

beforeEach(function () {
    $this->withoutVite();

    $this->tenantA = Tenant::factory()->create(['slug' => 'a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'b']);

    $this->userA = User::factory()->create();
    $this->userB = User::factory()->create();
    inTenant($this->tenantA, fn () => Staff::factory()->create(['user_id' => $this->userA->id]));
    inTenant($this->tenantB, fn () => Staff::factory()->create(['user_id' => $this->userB->id]));
});

it('lets a member open their tenant dashboard and 404s on another tenant', function () {
    $this->actingAs($this->userA)->get('/app/a/dashboard')->assertOk();
    $this->actingAs($this->userA)->get('/app/b/dashboard')->assertNotFound();
});

it('sends guests of a tenant route to login', function () {
    $this->get('/app/a/dashboard')->assertRedirect('/login');
});

it('redirects /dashboard to the last tenant, falling back to a membership', function () {
    $this->actingAs($this->userA)->get('/dashboard')->assertRedirect('/app/a/dashboard');

    // A stale last_tenant_id pointing at a tenant the user left must not be honoured.
    $this->userA->forceFill(['last_tenant_id' => $this->tenantB->id])->save();
    $this->actingAs($this->userA)->get('/dashboard')->assertRedirect('/app/a/dashboard');

    $loner = User::factory()->create();
    $this->actingAs($loner)->get('/dashboard')->assertRedirect('/');
});

it('uses one global identity per email', function () {
    // There is no sign-up: an invitation to an existing e-mail never creates a second user.
    $shop = provisionShop(['ownerEmail' => $this->userA->email]);

    $this->post("/invite/{$shop->token}", ['name' => 'Copy', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', $this->userA->email)->count())->toBe(1);
    $this->get('/register')->assertNotFound();
});

it('keeps the profile email unique across all users', function () {
    $this->actingAs($this->userA)
        ->patch('/settings/profile', ['name' => 'A', 'email' => $this->userB->email])
        ->assertSessionHasErrors('email');
});
