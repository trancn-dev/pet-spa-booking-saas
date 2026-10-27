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
    $payload = [
        'name' => 'New',
        'email' => 'fresh@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->post('/register', $payload)->assertRedirect('/dashboard');
    auth()->logout();

    $this->post('/register', $payload)->assertSessionHasErrors('email');
});

it('keeps the profile email unique across all users', function () {
    $this->actingAs($this->userA)
        ->patch('/settings/profile', ['name' => 'A', 'email' => $this->userB->email])
        ->assertSessionHasErrors('email');
});
