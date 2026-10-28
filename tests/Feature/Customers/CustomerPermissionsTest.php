<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Species;

/*
| Task 5.1: who may do what with customers and pets (design section 8, config/permissions.php).
|   view customers & pets: every role but inventory
|   create/edit customers: owner, branch_manager, receptionist
|   create/edit pets:      owner, branch_manager, receptionist, vet
|   delete:                owner only
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop();
    $this->tenant = $this->shop->tenant;
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    [$this->lan, $this->pet] = inTenant($this->tenant, function () {
        $lan = Customer::factory()->create();

        return [$lan, Pet::factory()->create(['customer_id' => $lan->id, 'species_id' => $this->dog->id])];
    });
    $this->as = fn (string $role) => $this->actingAs(memberWithRoles($this->tenant, [$role], [$this->q1]));
});

it('lets each role see customers and pets as the matrix says', function (string $role, int $status) {
    ($this->as)($role)->get('/app/paw-test/customers')->assertStatus($status);
    ($this->as)($role)->get("/app/paw-test/customers/{$this->lan->public_id}")->assertStatus($status);
    ($this->as)($role)->get("/app/paw-test/pets/{$this->pet->public_id}")->assertStatus($status);
    ($this->as)($role)->getJson('/app/paw-test/customers/search?q=ab')->assertStatus($status);
})->with([
    ['owner', 200], ['branch_manager', 200], ['receptionist', 200], ['cashier', 200], ['vet', 200], ['groomer', 200],
    ['inventory', 403],
]);

it('lets only front-desk roles create and edit customers', function (string $role, bool $allowed) {
    $create = ($this->as)($role)->post('/app/paw-test/customers', ['name' => 'Mới', 'phone' => '0909'.random_int(100000, 999999)]);
    $edit = ($this->as)($role)->put("/app/paw-test/customers/{$this->lan->public_id}", ['name' => 'Đổi', 'phone' => $this->lan->phone]);

    if ($allowed) {
        $create->assertRedirect();
        $edit->assertRedirect();
    } else {
        $create->assertForbidden();
        $edit->assertForbidden();
    }
})->with([
    ['owner', true], ['branch_manager', true], ['receptionist', true],
    ['cashier', false], ['vet', false], ['groomer', false], ['inventory', false],
]);

it('lets vets and front-desk roles add and edit pets', function (string $role, bool $allowed) {
    $create = ($this->as)($role)->post("/app/paw-test/customers/{$this->lan->public_id}/pets", ['name' => 'Bé', 'species_id' => $this->dog->id, 'size' => 's']);
    $stray = ($this->as)($role)->post('/app/paw-test/pets/strays', ['species_id' => $this->dog->id]);
    $edit = ($this->as)($role)->put("/app/paw-test/pets/{$this->pet->public_id}", ['name' => 'Đổi', 'species_id' => $this->dog->id, 'size' => 'm']);

    foreach ([$create, $stray, $edit] as $response) {
        $allowed ? $response->assertRedirect() : $response->assertForbidden();
    }
})->with([
    ['owner', true], ['branch_manager', true], ['receptionist', true], ['vet', true],
    ['cashier', false], ['groomer', false], ['inventory', false],
]);

it('lets only the owner delete customers and pets', function (string $role) {
    ($this->as)($role)->delete("/app/paw-test/pets/{$this->pet->public_id}")->assertForbidden();
    ($this->as)($role)->delete("/app/paw-test/customers/{$this->lan->public_id}")->assertForbidden();

    expect($this->lan->refresh()->trashed())->toBeFalse();
})->with(['branch_manager', 'receptionist', 'cashier', 'vet', 'groomer']);

it('keeps a shop that is suspended read-only for customers', function () {
    $receptionist = memberWithRoles($this->tenant, ['receptionist'], [$this->q1]);
    $this->tenant->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($receptionist)->get('/app/paw-test/customers')->assertOk();
    $this->actingAs($receptionist)->post('/app/paw-test/customers', ['name' => 'Mới', 'phone' => '0909111222'])->assertStatus(423);
});
