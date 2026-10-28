<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Rosters\SaveBranchClosure;
use App\Actions\Rosters\SaveOnlineBookingRate;
use App\Actions\Rosters\SaveWorkingHour;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\OnlineBookingRate;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Scheduling\OnlineRates;
use App\Scheduling\Roster;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.3: S8 rosters, time off, closed days, online booking rates.
| 2026-11-02 is a Monday.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop(['plan' => Plan::factory()->create(['max_branches' => 3, 'max_staff' => 20])]);
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->q3 = inTenant($this->tenant, fn () => Branch::factory()->create(['code' => 'Q3', 'name' => 'CN Quận 3']));
    $this->staffOf = fn (User $u): Staff => inTenant($this->tenant, fn () => Staff::query()->where('user_id', $u->id)->sole());
    $this->ha = ($this->staffOf)(memberWithRoles($this->tenant, ['vet', 'groomer'], [$this->q1, $this->q3]));
    $this->slot = fn (Staff $s, array $d, ?StaffWorkingHour $existing = null) => inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($s, $existing, [
        'branch_id' => $this->q1->id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '12:00', 'service_group' => 'medical',
        'effective_from' => null, 'effective_to' => null, ...$d,
    ]));
});

it('refuses two overlapping slots of one person, even at another branch', function () {
    ($this->slot)($this->ha, []);

    expect(fn () => ($this->slot)($this->ha, ['branch_id' => $this->q3->id, 'start_time' => '11:00', 'end_time' => '15:00', 'service_group' => 'grooming']))
        ->toThrow(ValidationException::class, 'Trùng giờ với khung 08:00–12:00 ở CN Quận 1 của người này.');

    // Back to back, another weekday, or effective ranges that do not meet: fine.
    ($this->slot)($this->ha, ['branch_id' => $this->q3->id, 'start_time' => '12:00', 'end_time' => '17:00', 'service_group' => 'grooming']);
    ($this->slot)($this->ha, ['weekday' => 2, 'start_time' => '09:00']);
    $old = ($this->slot)($this->ha, ['weekday' => 3, 'effective_to' => '2026-10-31']);
    ($this->slot)($this->ha, ['weekday' => 3, 'effective_from' => '2026-11-01']);

    expect(fn () => ($this->slot)($this->ha, ['weekday' => 3, 'start_time' => '10:00', 'effective_from' => '2026-10-15', 'effective_to' => '2026-10-20']))
        ->toThrow(ValidationException::class)
        // editing a slot does not clash with itself
        ->and(($this->slot)($this->ha, ['weekday' => 3, 'end_time' => '11:00', 'effective_to' => '2026-10-31'], $old)->end_time)->toBe('11:00:00');
});

it('refuses a slot at a branch the person does not work at, and an end before the start', function () {
    $q7 = inTenant($this->tenant, fn () => Branch::factory()->create(['code' => 'Q7']));

    expect(fn () => ($this->slot)($this->ha, ['branch_id' => $q7->id]))->toThrow(ValidationException::class)
        ->and(fn () => ($this->slot)($this->ha, ['start_time' => '12:00', 'end_time' => '08:00']))->toThrow(ValidationException::class);
});

it('counts a person with the vet and groomer roles once, in the slot group', function () {
    ($this->slot)($this->ha, ['service_group' => 'medical']);
    $groomer = ($this->staffOf)(memberWithRoles($this->tenant, ['groomer'], [$this->q1]));
    ($this->slot)($groomer, ['service_group' => 'grooming']);
    $cashier = ($this->staffOf)(memberWithRoles($this->tenant, ['cashier'], [$this->q1]));
    ($this->slot)($cashier, ['service_group' => null]);

    $counts = inTenant($this->tenant, fn () => (new Roster(ShopClock::for($this->tenant)))->onDutyPerDay($this->q1->id, '2026-11-02', '2026-11-03'));

    expect($counts['2026-11-02'])->toBe(['medical' => 1, 'grooming' => 1])
        ->and($counts['2026-11-03'])->toBe(['medical' => 0, 'grooming' => 0]);
});

it('drops slots covered by time off and closed days', function () {
    ($this->slot)($this->ha, []);
    $roster = fn () => inTenant($this->tenant, fn () => (new Roster(ShopClock::for($this->tenant)))->onDutyPerDay($this->q1->id, '2026-11-02', '2026-11-09'));

    inTenant($this->tenant, fn () => StaffTimeOff::query()->create([
        'staff_id' => $this->ha->id, 'starts_at' => ShopClock::for($this->tenant)->toUtc('2026-11-02', '00:00'), 'ends_at' => ShopClock::for($this->tenant)->toUtc('2026-11-03', '00:00'),
    ]));
    inTenant($this->tenant, fn () => app(SaveBranchClosure::class)->handle($this->q1->id, '2026-11-09', 'Sửa điện'));

    expect($roster()['2026-11-02']['medical'])->toBe(0)
        ->and($roster()['2026-11-09']['medical'])->toBe(0);

    inTenant($this->tenant, fn () => BranchClosure::query()->delete());
    expect($roster()['2026-11-09']['medical'])->toBe(1);
});

it('turns a 09:00–17:00 slot into the right UTC times across a DST switch', function () {
    $this->tenant->forceFill(['timezone' => 'America/New_York'])->save();
    ($this->slot)($this->ha, ['weekday' => 7, 'start_time' => '09:00', 'end_time' => '17:00']);

    $occurrences = inTenant($this->tenant, fn () => (new Roster(ShopClock::for($this->tenant)))->occurrences($this->q1->id, '2026-03-01', '2026-03-08'));

    expect(collect($occurrences)->map(fn ($o) => [$o['date'], $o['starts_at']->format('H:i'), $o['ends_at']->format('H:i')])->all())->toBe([
        ['2026-03-01', '14:00', '22:00'],
        ['2026-03-08', '13:00', '21:00'],
    ])->and(inTenant($this->tenant, fn () => (new Roster(ShopClock::for($this->tenant)))->onDutyAt($this->q1->id, ServiceGroup::Medical, CarbonImmutable::parse('2026-03-08T13:30:00Z'))))->toBe(1);
});

it('reports appointments assigned to someone taking time off, without cancelling them', function () {
    ($this->slot)($this->ha, []);
    $item = inTenant($this->tenant, function () {
        $clock = ShopClock::for($this->tenant);
        $appointment = Appointment::factory()->create(['branch_id' => $this->q1->id, 'starts_at' => $clock->toUtc('2026-11-02', '09:00'), 'ends_at' => $clock->toUtc('2026-11-02', '09:30')]);

        return AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'assigned_staff_id' => $this->ha->id]);
    });

    $this->actingAs($this->owner)->post('/app/paw-test/rosters/time-off', [
        'staff_id' => $this->ha->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-02', 'reason' => 'Ốm',
    ])->assertSessionHas('status', fn (string $s) => str_contains($s, '1 lịch'));

    $off = inTenant($this->tenant, fn () => StaffTimeOff::query()->sole());
    expect($off->starts_at->toIso8601String())->toBe('2026-11-01T17:00:00+00:00') // 00:00 in Vietnam
        ->and($off->ends_at->toIso8601String())->toBe('2026-11-02T17:00:00+00:00')
        ->and($item->refresh()->status->value)->toBe('booked');

    $this->actingAs($this->owner)->get('/app/paw-test/rosters?week=2026-11-02')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('people', fn ($people) => collect($people)->firstWhere('id', $this->ha->id)['time_off'][0]['affected'] === 1));
});

it('refuses a second closure for the same day, including a whole-shop one', function () {
    $close = fn (?int $branch) => inTenant($this->tenant, fn () => app(SaveBranchClosure::class)->handle($branch, '2026-11-09', null));

    $close(null);
    expect(fn () => $close(null))->toThrow(ValidationException::class)
        ->and(fn () => $close($this->q1->id))->toThrow(ValidationException::class);
});

it('picks the most specific online rate and rounds the maximum down', function () {
    $save = fn (array $d) => inTenant($this->tenant, fn () => app(SaveOnlineBookingRate::class)->handle($this->q1, null, [
        'service_group' => 'medical', 'ratio' => '0.5', 'weekday' => null, 'start_time' => null, 'end_time' => null, ...$d,
    ]));
    $save([]);
    $save(['weekday' => 6, 'ratio' => '0.25']);
    $save(['weekday' => 6, 'start_time' => '17:00', 'end_time' => '20:00', 'ratio' => '1']);
    $save(['start_time' => '07:00', 'end_time' => '09:00', 'ratio' => '0']);

    $rates = new OnlineRates;
    $ratio = fn (int $weekday, ?string $time) => inTenant($this->tenant, fn () => $rates->ratio($this->q1->id, ServiceGroup::Medical, $weekday, $time));

    expect($ratio(1, '10:00'))->toBe('0.500')
        ->and($ratio(6, '10:00'))->toBe('0.250')
        ->and($ratio(6, '18:00'))->toBe('1.000')
        ->and($ratio(1, '08:00'))->toBe('0.000')
        ->and(inTenant($this->tenant, fn () => $rates->ratio($this->q1->id, ServiceGroup::Grooming, 1)))->toBeNull()
        ->and(OnlineRates::maxOnline(3, '0.500'))->toBe(1)
        ->and(OnlineRates::maxOnline(3, '0.333'))->toBe(1) // design S12: 3 vets × 1/3 = 1
        ->and(OnlineRates::maxOnline(6, '0.333'))->toBe(2)
        ->and(OnlineRates::maxOnline(2, '0.333'))->toBe(0)
        ->and(OnlineRates::maxOnline(1, '0.999'))->toBe(0)
        ->and(OnlineRates::maxOnline(4, '0.750'))->toBe(3)
        ->and(OnlineRates::maxOnline(5, null))->toBe(0)
        ->and(fn () => $save(['weekday' => 6, 'ratio' => '0.3']))->toThrow(ValidationException::class, SaveOnlineBookingRate::DUPLICATE);
});

it('shows the S8 week with people on duty and the online maximum', function () {
    ($this->slot)($this->ha, []);
    inTenant($this->tenant, fn () => OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'ratio' => '1.000']));

    $this->actingAs($this->owner)->get('/app/paw-test/rosters?week=2026-11-04')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Rosters/Index')
            ->where('week.from', '2026-11-02')
            ->where('days.0.on_duty', ['medical' => 1, 'grooming' => 0])
            ->where('days.0.max_online.medical', 1)
            ->where('rates.0.default', '1.000')
            ->where('can.manage', true));
});

it('lets people without rosters.view see only their own row', function () {
    $cashierUser = memberWithRoles($this->tenant, ['cashier'], [$this->q1]);
    ($this->slot)($this->ha, []);

    $this->actingAs($cashierUser)->get('/app/paw-test/rosters')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('people', 1)->where('people.0.id', ($this->staffOf)($cashierUser)->id)->where('can.manage', false));
});

it('lets roster managers edit, others not', function (string $role, bool $allowed) {
    $user = memberWithRoles($this->tenant, [$role], [$this->q1]);

    $response = $this->actingAs($user)->post('/app/paw-test/rosters/slots', [
        'staff_id' => $this->ha->id, 'weekday' => 4, 'start_time' => '08:00', 'end_time' => '12:00', 'service_group' => 'medical',
    ]);
    $rates = $this->actingAs($user)->post("/app/paw-test/settings/branches/{$this->q1->public_id}/online-rates", ['service_group' => 'medical', 'ratio' => '0.5']);

    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    $allowed ? $rates->assertRedirect() : $rates->assertForbidden();
})->with([['owner', true], ['branch_manager', true], ['receptionist', false], ['vet', false], ['cashier', false]]);

it('keeps another shop roster out of reach', function () {
    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);
    [$slot, $off, $closure, $rate, $otherBranch] = inTenant($other->tenant, function () {
        $branch = Branch::query()->sole();
        $staff = Staff::factory()->create(['primary_branch_id' => $branch->id]);

        return [
            StaffWorkingHour::factory()->create(['staff_id' => $staff->id, 'branch_id' => $branch->id]),
            StaffTimeOff::query()->create(['staff_id' => $staff->id, 'starts_at' => now(), 'ends_at' => now()->addDay()]),
            BranchClosure::query()->create(['branch_id' => $branch->id, 'date' => '2026-12-01']),
            OnlineBookingRate::factory()->create(['branch_id' => $branch->id]),
            $branch,
        ];
    });

    assertTenantIsolated($this->owner, [
        "PUT /app/paw-test/rosters/slots/{$slot->id}",
        "DELETE /app/paw-test/rosters/slots/{$slot->id}",
        "DELETE /app/paw-test/rosters/time-off/{$off->id}",
        "DELETE /app/paw-test/rosters/closures/{$closure->id}",
        "GET /app/paw-test/settings/branches/{$otherBranch->public_id}/online-rates",
        "PUT /app/paw-test/settings/branches/{$this->q1->public_id}/online-rates/{$rate->id}",
    ], ['weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00', 'service_group' => 'medical', 'ratio' => '0.5']);

    // A person of the other shop cannot be given a slot here.
    $this->actingAs($this->owner)->post('/app/paw-test/rosters/slots', [
        'staff_id' => $slot->staff_id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:00',
    ])->assertNotFound();
});
