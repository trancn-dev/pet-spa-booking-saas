<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
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

it('turns an Inertia visit into a full page load when it enters or switches shops', function () {
    $inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request())];

    // coming from the login page (no shop in the URL): full load so Ziggy gets the shop slug
    $this->actingAs($this->userA)->withHeaders($inertia + ['Referer' => 'http://localhost/login'])->get('/app/a/dashboard')
        ->assertStatus(409)->assertHeader('X-Inertia-Location');

    // from another shop
    $this->actingAs($this->userA)->withHeaders($inertia + ['Referer' => 'http://localhost/app/b/dashboard'])->get('/app/a/dashboard')
        ->assertStatus(409);

    // inside the same shop: a normal Inertia response
    $this->actingAs($this->userA)->withHeaders($inertia + ['Referer' => 'http://localhost/app/a/customers'])->get('/app/a/dashboard')
        ->assertOk();
});
