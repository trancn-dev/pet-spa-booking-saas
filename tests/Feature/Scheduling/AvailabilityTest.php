<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Rosters\SaveWorkingHour;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\PetSize;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\OnlineBookingRate;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Species;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Pricing\PetProfile;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingLine;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/*
| Task 5.4: free starts by capacity. Shop in Asia/Ho_Chi_Minh, branch open 08:00–20:00,
| 2026-11-02 is a Monday; "now" is a week earlier so the lead time never interferes.
*/

beforeEach(function () {
    $this->shop = provisionShop(['plan' => Plan::factory()->create(['max_staff' => 50])]);
    $this->tenant = $this->shop->tenant;
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->day = '2026-11-02';
    $this->now = CarbonImmutable::parse('2026-10-26T00:00:00Z');
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    [$this->exam, $this->spa] = inTenant($this->tenant, fn () => [
        Service::factory()->create(['code' => 'KHAM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'buffer_min' => 0]),
        Service::factory()->create(['code' => 'SPA', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 60, 'buffer_min' => 0]),
    ]);

    $this->person = fn (array $roles = ['vet']): Staff => inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, $roles, [$this->q1])->id)->sole());
    $this->slot = fn (Staff $s, string $from, string $to, ?string $group = 'medical', int $weekday = 1) => inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($s, null, [
        'branch_id' => $this->q1->id, 'weekday' => $weekday, 'start_time' => $from, 'end_time' => $to, 'service_group' => $group, 'effective_from' => null, 'effective_to' => null,
    ]));
    $this->vets = function (int $n, string $from = '08:00', string $to = '17:00'): array {
        $people = [];
        for ($i = 0; $i < $n; $i++) {
            $people[] = $p = ($this->person)();
            ($this->slot)($p, $from, $to);
        }

        return $people;
    };
    $this->rate = fn (string $ratio, string $group = 'medical', array $extra = []) => inTenant($this->tenant, fn () => OnlineBookingRate::factory()->create([
        'branch_id' => $this->q1->id, 'service_group' => $group, 'ratio' => $ratio, ...$extra,
    ]));
    $this->book = function (string $time, int $minutes = 30, AppointmentSource $source = AppointmentSource::Online, string $group = 'medical', AppointmentStatus $status = AppointmentStatus::Confirmed, AppointmentItemStatus $itemStatus = AppointmentItemStatus::Booked, int $lines = 1): void {
        inTenant($this->tenant, function () use ($time, $minutes, $source, $group, $status, $itemStatus, $lines) {
            $start = ShopClock::for($this->tenant)->toUtc($this->day, $time);
            $appointment = Appointment::factory()->create(['branch_id' => $this->q1->id, 'starts_at' => $start, 'ends_at' => $start->addMinutes($minutes), 'source' => $source, 'status' => $status]);
            for ($i = 0; $i < $lines; $i++) {
                AppointmentItem::factory()->create([
                    'appointment_id' => $appointment->id, 'service_id' => $group === 'medical' ? $this->exam->id : $this->spa->id, 'service_group' => $group,
                    'starts_at' => $start, 'ends_at' => $start->addMinutes($minutes), 'status' => $itemStatus,
                ]);
            }
        });
    };
    $this->line = fn (?Service $service = null) => new BookingLine($service ?? $this->exam, new PetProfile($this->dog->id, null, PetSize::M));
    $this->free = fn (array $lines, ?string $day = null) => inTenant($this->tenant, fn () => array_column(
        app(AvailabilityService::class)->slotsForOnline($this->q1->refresh(), $lines, $day ?? $this->day, $day ?? $this->day, $this->now)[$day ?? $this->day], 'time',
    ));
});

it('gives 1 online seat for 3 vets at 1/3', function () {
    ($this->vets)(3);
    ($this->rate)('0.333');
    ($this->book)('09:00');

    $free = ($this->free)([($this->line)()]);

    expect($free)->toContain('08:00', '08:30', '09:30', '16:30')
        ->not->toContain('08:45', '09:00', '09:15', '16:45', '17:00');
});

it('drops to 0 seats where one of the 3 vets is off (floor 2 × 1/3)', function () {
    [$ha] = ($this->vets)(3);
    ($this->rate)('0.333');
    $clock = ShopClock::for($this->tenant);
    inTenant($this->tenant, fn () => StaffTimeOff::query()->create(['staff_id' => $ha->id, 'starts_at' => $clock->toUtc($this->day, '13:00'), 'ends_at' => $clock->toUtc($this->day, '17:00')]));

    $free = ($this->free)([($this->line)()]);

    expect($free)->toContain('08:00', '12:30')->not->toContain('12:45', '13:00', '15:00');
});

it('applies a rate override for some hours', function () {
    ($this->vets)(3);
    ($this->rate)('0.333');
    ($this->rate)('1', 'medical', ['weekday' => 1, 'start_time' => '12:00:00', 'end_time' => '14:00:00']);
    ($this->book)('12:00', lines: 2);
    ($this->book)('10:00');

    expect(($this->free)([($this->line)()]))->toContain('12:00', '13:30')->not->toContain('10:00', '13:45');
});

it('does not take online seats for front-desk bookings, nor for cancelled or no-show lines', function () {
    ($this->vets)(1);
    ($this->rate)('1');
    ($this->book)('09:00', source: AppointmentSource::Staff);
    ($this->book)('10:00', status: AppointmentStatus::Cancelled, itemStatus: AppointmentItemStatus::Cancelled);
    ($this->book)('11:00', itemStatus: AppointmentItemStatus::NoShow);
    ($this->book)('12:00', itemStatus: AppointmentItemStatus::CheckedIn);

    expect(($this->free)([($this->line)()]))->toContain('09:00', '10:00', '11:00')->not->toContain('12:00');
});

it('needs two seats for two pets examined together', function () {
    ($this->vets)(3);
    ($this->rate)('0.667'); // 2 seats
    ($this->book)('10:00');

    expect(($this->free)([($this->line)()]))->toContain('10:00')
        ->and(($this->free)([($this->line)(), ($this->line)()]))->not->toContain('10:00')->toContain('10:30');
});

it('checks both groups for an exam and a spa starting together', function () {
    ($this->vets)(1);
    ($this->rate)('1');
    $groomer = ($this->person)(['groomer']);
    ($this->slot)($groomer, '08:00', '12:00', 'grooming');

    // No grooming rate: never online.
    expect(($this->free)([($this->line)(), ($this->line)($this->spa)]))->toBe([]);

    ($this->rate)('1', 'grooming');
    $free = ($this->free)([($this->line)(), ($this->line)($this->spa)]); // both lines start together; the 60' spa must end by 12:00

    expect($free)->toContain('08:00', '11:00')->not->toContain('11:15', '12:00');
});

it('counts a person with the vet and groomer roles once, in the group of the slot', function () {
    $both = ($this->person)(['vet', 'groomer']);
    ($this->slot)($both, '08:00', '12:00', 'medical');
    ($this->rate)('1');
    ($this->rate)('1', 'grooming');

    expect(($this->free)([($this->line)()]))->toContain('09:00')
        ->and(($this->free)([($this->line)($this->spa)]))->toBe([]);
});

it('follows split shifts', function () {
    $vet = ($this->person)();
    ($this->slot)($vet, '08:00', '11:00');
    ($this->slot)($vet, '14:00', '17:00');
    ($this->rate)('1');

    expect(($this->free)([($this->line)()]))->toContain('10:30', '14:00', '16:30')->not->toContain('10:45', '11:00', '13:45');
});

it('offers nothing on a closed day, or outside opening hours', function () {
    ($this->vets)(1, '06:00', '22:00');
    ($this->rate)('1');

    $free = ($this->free)([($this->line)()]);
    expect($free[0])->toBe('08:00')->and(end($free))->toBe('19:30');

    inTenant($this->tenant, fn () => BranchClosure::query()->create(['branch_id' => $this->q1->id, 'date' => $this->day]));
    expect(($this->free)([($this->line)()]))->toBe([]);
});

it('skips starts before now plus the lead time', function () {
    ($this->vets)(1);
    ($this->rate)('1');
    $this->now = ShopClock::for($this->tenant)->toUtc($this->day, '08:10'); // + 60' = 09:10

    expect(($this->free)([($this->line)()])[0])->toBe('09:15');
});

it('places starts on the right UTC instants across a DST switch', function () {
    $this->tenant->forceFill(['timezone' => 'America/New_York'])->save();
    $vet = ($this->person)();
    ($this->slot)($vet, '09:00', '17:00', 'medical', 7);
    ($this->rate)('1');
    $this->now = CarbonImmutable::parse('2026-03-01T00:00:00Z');

    $slots = inTenant($this->tenant, fn () => app(AvailabilityService::class)->slotsForOnline($this->q1->refresh(), [($this->line)()], '2026-03-07', '2026-03-08', $this->now));

    expect($slots['2026-03-07'])->toBe([]) // a Saturday: nobody on duty
        ->and($slots['2026-03-08'][0])->toBe(['time' => '09:00', 'starts_at' => '2026-03-08T13:00:00Z'])
        ->and(end($slots['2026-03-08']))->toBe(['time' => '16:30', 'starts_at' => '2026-03-08T20:30:00Z']);
});

it('shows the front desk everything with people on duty, lines taken and an over flag', function () {
    ($this->vets)(1);
    ($this->rate)('1');
    ($this->book)('09:00', source: AppointmentSource::Staff);

    $slots = inTenant($this->tenant, fn () => collect(app(AvailabilityService::class)->slotsForStaff($this->q1->refresh(), [($this->line)()], $this->day, $this->day)[$this->day])->keyBy('time'));

    expect($slots->has('08:00'))->toBeTrue()->and($slots->has('19:30'))->toBeTrue()
        ->and($slots['09:00']['over'])->toBeTrue()
        ->and($slots['09:00']['groups']['medical'])->toBe(['on_duty' => 1, 'taken' => 1, 'online_taken' => 0, 'online_capacity' => 1])
        ->and($slots['09:30']['over'])->toBeFalse()
        ->and($slots['18:00']['over'])->toBeTrue(); // nobody on duty after 17:00
});

it('loads the range in a fixed number of queries, whatever the staff count', function () {
    ($this->rate)('0.5');
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        inTenant($this->tenant, fn () => app(AvailabilityService::class)->slotsForOnline($this->q1->refresh(), [($this->line)(), ($this->line)($this->spa)], '2026-11-02', '2026-11-08', $this->now));

        return count(DB::getQueryLog());
    };

    ($this->vets)(2);
    $few = $count();
    ($this->vets)(8);
    $many = $count();

    expect($many)->toBe($few)->and($few)->toBeLessThan(15);
});

it('answers S2 with JSON for the current branch', function () {
    $this->withoutVite();
    $owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $owner);
    ($this->vets)(1);
    ($this->rate)('1');

    $this->travelTo($this->now);
    $this->actingAs($owner)->getJson("/app/paw-test/availability?mode=online&from={$this->day}&to={$this->day}&lines[0][service_id]={$this->exam->id}&lines[0][species_id]={$this->dog->id}&lines[0][size]=m")
        ->assertOk()->assertJsonPath("data.{$this->day}.0.time", '08:00')->assertJsonPath('timezone', 'Asia/Ho_Chi_Minh');

    $this->actingAs($owner)->getJson("/app/paw-test/availability?from={$this->day}")
        ->assertOk()->assertJsonPath("data.{$this->day}.0.groups.medical.on_duty", 1);

    $inventory = memberWithRoles($this->tenant, ['inventory'], [$this->q1]);
    $this->actingAs($inventory)->getJson('/app/paw-test/availability')->assertForbidden();
});
