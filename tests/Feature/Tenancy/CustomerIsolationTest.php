<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.1: one shop's customers and pets never reach another shop, by URL, list or search.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->mine = provisionShop();
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->mine->tenant, $this->mine->invitation, $this->owner);

    $this->theirs = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'owner@other.test']);
    $dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    [$this->their, $this->theirPet] = inTenant($this->theirs->tenant, function () use ($dog) {
        $c = Customer::factory()->create(['name' => 'Khách Bên Kia', 'phone' => '+84901234567']);

        return [$c, Pet::factory()->create(['customer_id' => $c->id, 'species_id' => $dog->id, 'name' => 'Pet Bên Kia'])];
    });
    $this->dogId = $dog->id;
});

it('404s every customer and pet URL of another shop', function () {
    $c = $this->their->public_id;
    $p = $this->theirPet->public_id;

    assertTenantIsolated($this->owner, [
        "GET /app/paw-test/customers/{$c}",
        "GET /app/paw-test/customers/{$c}/edit",
        "PUT /app/paw-test/customers/{$c}",
        "DELETE /app/paw-test/customers/{$c}",
        "GET /app/paw-test/customers/{$c}/pets/create",
        "POST /app/paw-test/customers/{$c}/pets",
        "GET /app/paw-test/pets/{$p}",
        "GET /app/paw-test/pets/{$p}/edit",
        "PUT /app/paw-test/pets/{$p}",
        "DELETE /app/paw-test/pets/{$p}",
    ], ['name' => 'Hack', 'phone' => '0901234567', 'species_id' => $this->dogId, 'size' => 'm']);

    expect($this->their->refresh()->name)->toBe('Khách Bên Kia')
        ->and($this->theirPet->refresh()->name)->toBe('Pet Bên Kia');
});

it('404s another shop entirely for someone who is not its member', function () {
    assertTenantIsolated($this->owner, [
        'GET /app/other-shop/customers',
        'GET /app/other-shop/customers/search?q=Khách',
        "GET /app/other-shop/customers/{$this->their->public_id}",
    ]);
});

it('never lists or finds another shop customer, even by the same phone', function () {
    inTenant($this->mine->tenant, fn () => Customer::factory()->create(['name' => 'Khách Của Tôi', 'phone' => '+84909999999']));

    $this->actingAs($this->owner)->get('/app/paw-test/customers')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('customers.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Khách Của Tôi']));

    foreach (['0901234567', '4567', 'Bên Kia', 'Pet'] as $q) {
        $this->actingAs($this->owner)->getJson('/app/paw-test/customers/search?q='.urlencode($q))->assertOk()->assertJsonCount(0, 'data');
    }
});

it('creates strays only in the own shop branch profile', function () {
    $this->actingAs($this->owner)->post('/app/paw-test/pets/strays', ['species_id' => $this->dogId])->assertRedirect();

    $myBranch = inTenant($this->mine->tenant, fn () => Branch::query()->sole());
    $stray = inTenant($this->mine->tenant, fn () => Pet::query()->with('customer')->sole());
    expect($stray->customer->system_branch_id)->toBe($myBranch->id)
        ->and(inTenant($this->theirs->tenant, fn () => Pet::query()->count()))->toBe(1);
});
