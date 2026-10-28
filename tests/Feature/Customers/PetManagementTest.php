<?php

declare(strict_types=1);

use App\Actions\Customers\DeletePet;
use App\Actions\Invitations\AcceptInvitation;
use App\Enums\PetSize;
use App\Enums\VisitStatus;
use App\Models\ActivityLog as ActivityLogEntry;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Species;
use App\Models\User;
use App\Models\Visit;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.1: pets inside their customer, size suggestion, safety alerts, S4, "Thú hoang #n".
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop();
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    $this->cat = Species::query()->firstOrCreate(['code' => 'cat'], ['name' => 'Mèo']);
    $this->golden = Breed::query()->create(['species_id' => $this->dog->id, 'name' => 'Golden Retriever', 'default_size' => PetSize::L]);
    $this->lan = inTenant($this->tenant, fn () => Customer::factory()->create(['name' => 'Lan']));
});

it('adds a pet with the breed size when no size is chosen, and logs the alerts', function () {
    $this->actingAs($this->owner)->post("/app/paw-test/customers/{$this->lan->public_id}/pets", [
        'name' => 'Vàng', 'species_id' => $this->dog->id, 'breed_id' => $this->golden->id, 'sex' => 'male',
        'allergies' => 'Penicillin', 'behavior_notes' => 'Sợ máy sấy',
    ])->assertRedirect();

    $pet = inTenant($this->tenant, fn () => Pet::query()->sole());
    expect($pet->size)->toBe(PetSize::L)
        ->and($pet->customer_id)->toBe($this->lan->id)
        ->and($pet->is_stray)->toBeFalse()
        ->and(inTenant($this->tenant, fn () => ActivityLogEntry::query()->where('action', 'pet.alerts_changed')->count()))->toBe(1);
});

it('sizes by weight over the breed, and keeps an explicit size', function () {
    $post = fn (array $data) => $this->actingAs($this->owner)->post("/app/paw-test/customers/{$this->lan->public_id}/pets", [
        'name' => 'Bé', 'species_id' => $this->dog->id, 'breed_id' => $this->golden->id, ...$data,
    ]);

    $post(['weight_kg' => '12']);
    $post(['weight_kg' => '12', 'size' => 'xl']);

    expect(inTenant($this->tenant, fn () => Pet::query()->orderBy('id')->pluck('size')->all()))->toBe([PetSize::M, PetSize::Xl]);
});

it('refuses a breed of another species and a size nobody can suggest', function () {
    $this->actingAs($this->owner)->post("/app/paw-test/customers/{$this->lan->public_id}/pets", [
        'name' => 'Mun', 'species_id' => $this->cat->id, 'breed_id' => $this->golden->id,
    ])->assertSessionHasErrors('breed_id');

    $this->actingAs($this->owner)->post("/app/paw-test/customers/{$this->lan->public_id}/pets", [
        'name' => 'Mun', 'species_id' => $this->cat->id,
    ])->assertSessionHasErrors('size');

    expect(inTenant($this->tenant, fn () => Pet::query()->count()))->toBe(0);
});

it('answers the size hint with its reason', function () {
    $this->actingAs($this->owner)->getJson("/app/paw-test/pets/size-suggestion?breed_id={$this->golden->id}")
        ->assertOk()->assertJsonPath('data.size', 'l')->assertJsonPath('data.reason', 'Cỡ thường gặp của giống Golden Retriever');

    $this->actingAs($this->owner)->getJson("/app/paw-test/pets/size-suggestion?species_id={$this->cat->id}&weight_kg=3.5")
        ->assertOk()->assertJsonPath('data.size', 's')->assertJsonPath('data.reason', 'Theo cân nặng 3,5 kg');

    $this->actingAs($this->owner)->getJson("/app/paw-test/pets/size-suggestion?species_id={$this->cat->id}")
        ->assertOk()->assertJsonPath('data', null);
});

it('edits a pet and logs only alert changes', function () {
    $pet = inTenant($this->tenant, fn () => Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $this->dog->id, 'name' => 'Vàng']));

    $this->actingAs($this->owner)->put("/app/paw-test/pets/{$pet->public_id}", [
        'name' => 'Vàng Anh', 'species_id' => $this->dog->id, 'size' => 'l', 'chronic_conditions' => 'Tim',
    ])->assertRedirect("/app/paw-test/pets/{$pet->public_id}");

    $log = inTenant($this->tenant, fn () => ActivityLogEntry::query()->where('action', 'pet.alerts_changed')->sole());
    expect($pet->refresh()->name)->toBe('Vàng Anh')->and($pet->size)->toBe(PetSize::L)
        ->and($log->changes)->toBe(['chronic_conditions' => 'Tim']);
});

it('shows the S4 profile with alerts, upcoming appointments and history per branch', function () {
    $pet = inTenant($this->tenant, function () {
        $pet = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $this->dog->id, 'birth_date' => now()->subMonths(27), 'allergies' => 'Gà']);
        Visit::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $this->lan->id, 'pet_id' => $pet->id, 'status' => VisitStatus::Done, 'reason' => 'Ho, sổ mũi']);
        $appointment = Appointment::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $this->lan->id]);
        AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'pet_id' => $pet->id]);

        return $pet;
    });

    $this->actingAs($this->owner)->get("/app/paw-test/pets/{$pet->public_id}?tab=history")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Pets/Show')
            ->where('tab', 'history')
            ->where('pet.age', '2 tuổi 3 tháng')
            ->where('pet.alerts.0.text', 'Gà')
            ->where('pet.customer.name', 'Lan')
            ->has('upcoming', 1)
            ->where('history.0.branch', 'CN Quận 1')
            ->where('history.0.summary', 'Ho, sổ mũi'));
});

it('hides the visit reason and the medical tab from a cashier', function () {
    $cashier = memberWithRoles($this->tenant, ['cashier'], [$this->q1]);
    $pet = inTenant($this->tenant, function () {
        $pet = Pet::factory()->create(['customer_id' => $this->lan->id]);
        Visit::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $this->lan->id, 'pet_id' => $pet->id, 'reason' => 'Bí mật']);

        return $pet;
    });

    $this->actingAs($cashier)->get("/app/paw-test/pets/{$pet->public_id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('history.0.summary', null)
            ->where('can.medicalTab', false)->where('can.vaccinationsTab', false)->where('can.update', false));
});

it('creates numbered strays at the current branch, hidden from the customer list', function () {
    $this->actingAs($this->owner)->post('/app/paw-test/pets/strays', ['species_id' => $this->dog->id, 'note' => 'Cắn người']);
    $this->actingAs($this->owner)->post('/app/paw-test/pets/strays', ['species_id' => $this->cat->id]);

    $strays = inTenant($this->tenant, fn () => Pet::query()->with('customer')->orderBy('id')->get());
    expect($strays->pluck('name')->all())->toBe(['Thú hoang #1', 'Thú hoang #2'])
        ->and($strays->every(fn (Pet $p) => $p->is_stray && $p->customer->is_system && $p->customer->system_branch_id === $this->q1->id))->toBeTrue()
        ->and($strays[0]->behavior_notes)->toBe('Cắn người');

    // Numbers are never reused, even after a delete.
    inTenant($this->tenant, fn () => $strays[1]->delete());
    $this->actingAs($this->owner)->post('/app/paw-test/pets/strays', ['species_id' => $this->dog->id]);
    expect(inTenant($this->tenant, fn () => Pet::query()->latest('id')->value('name')))->toBe('Thú hoang #3');

    $this->actingAs($this->owner)->get('/app/paw-test/customers?tab=strays')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->has('strays', 2)->where('strays.1.alerts.0.label', 'Lưu ý'));
});

it('refuses to delete a pet still booked, deletes it otherwise', function () {
    [$pet, $item] = inTenant($this->tenant, function () {
        $pet = Pet::factory()->create(['customer_id' => $this->lan->id]);
        $appointment = Appointment::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $this->lan->id]);

        return [$pet, AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'pet_id' => $pet->id])];
    });

    $this->actingAs($this->owner)->delete("/app/paw-test/pets/{$pet->public_id}")->assertSessionHasErrors(['pet' => DeletePet::HAS_UPCOMING]);

    inTenant($this->tenant, fn () => $item->update(['status' => 'cancelled']));
    $this->actingAs($this->owner)->delete("/app/paw-test/pets/{$pet->public_id}")->assertRedirect("/app/paw-test/customers/{$this->lan->public_id}");
    expect(inTenant($this->tenant, fn () => Pet::withTrashed()->find($pet->id)?->trashed()))->toBeTrue();
});
