<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\SessionGuard;

/*
| MANDATORY: auth must never cross tenant boundaries.
| Never delete or weaken these; extend them when adding auth features.
*/

beforeEach(function () {
    config(['tenancy.central_domain' => 'spa.test']);
    $this->withoutVite();

    $this->tenantA = Tenant::factory()->create(['slug' => 'a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'b']);

    $this->userA = User::factory()->for($this->tenantA)->create(['email' => 'same@example.com']);
    $this->userB = User::factory()->for($this->tenantB)->create(['email' => 'same@example.com']);

    // What a logged-in browser session holds: the user id under the guard's key.
    $this->sessionFor = fn (User $user) => [
        'login_web_'.sha1(SessionGuard::class) => $user->getKey(),
    ];
});

it('does not honour a session of tenant A on the host of tenant B', function () {
    $session = ($this->sessionFor)($this->userA);

    $this->withSession($session)->get('http://a.spa.test/dashboard')->assertOk();

    // The test app reuses one guard instance across requests; production boots fresh per request.
    auth()->forgetGuards();

    // Same session replayed against tenant B must not resolve to userA.
    $this->withSession($session)->get('http://b.spa.test/dashboard')->assertRedirect('/login');
});

it('does not log in with credentials that belong to another tenant', function () {
    $other = User::factory()->for($this->tenantA)->create(['email' => 'only-a@example.com']);

    $this->post('http://b.spa.test/login', ['email' => $other->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs in the user of the host tenant when the same email exists in both tenants', function () {
    $this->post('http://b.spa.test/login', ['email' => 'same@example.com', 'password' => 'password']);

    expect(auth()->id())->toBe($this->userB->id);
});

it('allows the same email to register in different tenants but not twice in one', function () {
    $payload = [
        'name' => 'New',
        'email' => 'fresh@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];

    $this->post('http://a.spa.test/register', $payload)->assertRedirect('/dashboard');
    auth()->logout();

    $this->post('http://b.spa.test/register', $payload)->assertRedirect('/dashboard');
    auth()->logout();

    $this->post('http://a.spa.test/register', $payload)->assertSessionHasErrors('email');

    expect(User::withoutGlobalScopes()->where('email', 'fresh@example.com')->pluck('tenant_id')->sort()->values()->all())
        ->toBe([$this->tenantA->id, $this->tenantB->id]);
});

it('checks profile email uniqueness within the tenant only', function () {
    $mate = User::factory()->for($this->tenantA)->create(['email' => 'mate@example.com']);
    User::factory()->for($this->tenantB)->create(['email' => 'elsewhere@example.com']);

    $this->actingAs($this->userA)
        ->patch('http://a.spa.test/settings/profile', ['name' => 'A', 'email' => $mate->email])
        ->assertSessionHasErrors('email');

    $this->actingAs($this->userA)
        ->patch('http://a.spa.test/settings/profile', ['name' => 'A', 'email' => 'elsewhere@example.com'])
        ->assertSessionHasNoErrors();
});

it('returns 404 for auth pages on the central domain and unknown hosts', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'central login' => 'http://spa.test/login',
    'central register' => 'http://spa.test/register',
    'unknown tenant' => 'http://nope.spa.test/login',
]);
