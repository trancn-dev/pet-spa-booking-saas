<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Rosters\SaveWorkingHour;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\OnlineBookingRate;
use App\Models\Pet;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Species;
use App\Models\Staff;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.7: JSON / Inertia endpoints behind the calendar (S1), booking drawer (S2) and detail drawer (S3).
| "Now" is Monday 2026-11-02 08:30 local; one vet and one groomer on duty 08:00–17:00.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-02T01:30:00Z'));
    $shop = provisionShop(['plan' => Plan::factory()->create(['max_staff' => 50])]);
    $this->tenant = $shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);

    inTenant($this->tenant, function () use ($dog) {
        $this->exam = Service::factory()->create(['code' => 'KHAM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'buffer_min' => 0]);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Medical, 'ratio' => '1.000']);
        $this->lan = Customer::factory()->create();
        $this->mochi = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $dog->id, 'name' => 'Mochi']);
    });

    $this->vet = inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, ['vet'], [$this->q1])->id)->sole());
    foreach ([1, 2, 3] as $weekday) {
        inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($this->vet, null, [
            'branch_id' => $this->q1->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '17:00', 'service_group' => 'medical', 'effective_from' => null, 'effective_to' => null,
        ]));
    }

    $this->payload = fn (array $o = []) => [
        'customer_id' => $this->lan->public_id, 'date' => '2026-11-02', 'time' => '09:00',
        'items' => [['pet_id' => $this->mochi->public_id, 'service_id' => $this->exam->id]],
        'idempotency_key' => (string) Str::uuid(), ...$o,
    ];
});

it('renders the calendar page with the day, quota and form options', function () {
    $this->actingAs($this->owner)->get('/app/paw-test/appointments?date=2026-11-02&view=staff')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Appointments/Index')
            ->where('date', '2026-11-02')->where('today', '2026-11-02')->where('view', 'staff')
            ->has('day.staff', 1)->has('services', 1)->where('can.book', true));

    $this->actingAs($this->owner)->get('/app/paw-test/appointments?view=week&date=2026-11-04')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('day', null)->has('week', 7));
});

it('books over HTTP, replays the same key, and shows the booking in the day and the drawer', function () {
    $body = ($this->payload)();
    $created = $this->actingAs($this->owner)->postJson('/app/paw-test/appointments', $body)->assertCreated();
    $id = $created->json('appointment');

    $this->actingAs($this->owner)->postJson('/app/paw-test/appointments', $body)->assertOk()->assertJsonPath('appointment', $id);
    expect(inTenant($this->tenant, fn () => Appointment::query()->count()))->toBe(1);

    $this->actingAs($this->owner)->getJson('/app/paw-test/appointments/day?date=2026-11-02')
        ->assertOk()->assertJsonPath('data.blocks.0.appointment', $id)->assertJsonPath('data.blocks.0.start', '09:00');

    $this->actingAs($this->owner)->getJson("/app/paw-test/appointments/{$id}")
        ->assertOk()
        ->assertJsonPath('data.lines.0.pet.name', 'Mochi')
        ->assertJsonPath('data.history.0.action', 'Thêm dịch vụ')
        ->assertJsonPath('data.can.checkIn', true);
});

it('validates the booking body', function () {
    $this->actingAs($this->owner)->postJson('/app/paw-test/appointments', ($this->payload)(['items' => []]))
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->actingAs($this->owner)->postJson('/app/paw-test/appointments', ($this->payload)(['idempotency_key' => 'nope']))
        ->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
});

it('quotes duration and price for a pet, and lists who is on duty', function () {
    $this->actingAs($this->owner)->getJson('/app/paw-test/appointments/quotes?pet='.$this->mochi->public_id.'&services[]='.$this->exam->id)
        ->assertOk()->assertJsonPath("data.{$this->exam->id}.duration_min", 30);

    $this->actingAs($this->owner)->getJson('/app/paw-test/appointments/on-duty?date=2026-11-02')
        ->assertOk()->assertJsonPath('data.0.id', $this->vet->id);
});

it('creates a customer and a pet as JSON for the quick create in the drawer', function () {
    $c = $this->actingAs($this->owner)->postJson('/app/paw-test/customers', ['name' => 'Chị Mai', 'phone' => '0912345678'])->assertSuccessful();
    expect($c->json('data.id'))->toBeString();
});

it('keeps the calendar behind permissions and inside the shop', function () {
    $groomer = memberWithRoles($this->tenant, ['groomer'], [$this->q1]);
    $this->actingAs($groomer)->postJson('/app/paw-test/appointments', ($this->payload)())->assertForbidden();

    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);
    $foreign = inTenant($other->tenant, fn () => Appointment::factory()->create());
    assertTenantIsolated($this->owner, ["GET /app/paw-test/appointments/{$foreign->public_id}"]);
    $this->actingAs($this->owner)->postJson('/app/paw-test/appointments', ($this->payload)(['customer_id' => 'does-not-exist']))->assertNotFound();
});
