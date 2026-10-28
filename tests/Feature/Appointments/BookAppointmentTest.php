<?php

declare(strict_types=1);

use App\Actions\Appointments\BookAppointment;
use App\Actions\Rosters\SaveWorkingHour;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Booking\Exceptions\QuotaExceededException;
use App\Booking\Exceptions\SlotTakenException;
use App\Enums\AppointmentSource;
use App\Enums\ServiceGroup;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\Customer;
use App\Models\OnlineBookingRate;
use App\Models\Pet;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServicePriceRule;
use App\Models\Species;
use App\Models\Staff;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
| Task 5.5: BookAppointment, the rules (one process). The real races are in tests/Concurrency.
| Shop in Asia/Ho_Chi_Minh; 2026-11-02 is a Monday; 3 vets 08:00–17:00 at 1/3 = 1 online seat.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-26T01:00:00Z'));
    $this->plan = Plan::factory()->create(['max_staff' => 50, 'max_appointments_per_month' => 100]);
    $this->shop = provisionShop(['plan' => $this->plan]);
    $this->tenant = $this->shop->tenant;
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->clock = ShopClock::for($this->tenant);
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);

    inTenant($this->tenant, function () {
        $this->exam = Service::factory()->create(['code' => 'KHAM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'buffer_min' => 0, 'price' => '200000.00']);
        $this->vaccine = Service::factory()->create(['code' => 'TIEM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 15, 'buffer_min' => 0]);
        $this->spa = Service::factory()->create(['code' => 'SPA', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 60, 'buffer_min' => 15, 'price' => '300000.00']);
        ServicePriceRule::factory()->create(['service_id' => $this->spa->id, 'species_id' => $this->dog->id, 'size' => null, 'duration_min' => 90, 'price' => '350000.00']);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Medical, 'ratio' => '0.333']);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Grooming, 'ratio' => '1.000']);
        $this->lan = Customer::factory()->create(['name' => 'Lan']);
        $this->mochi = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $this->dog->id, 'name' => 'Mochi']);
        $this->bo = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $this->dog->id, 'name' => 'Bơ']);
        $this->binh = Customer::factory()->create(['name' => 'Bình']);
        $this->vang = Pet::factory()->create(['customer_id' => $this->binh->id, 'species_id' => $this->dog->id, 'name' => 'Vàng']);
    });

    $this->vets = [];
    foreach (range(1, 3) as $i) {
        $vet = inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, ['vet'], [$this->q1])->id)->sole());
        inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($vet, null, [
            'branch_id' => $this->q1->id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'service_group' => 'medical', 'effective_from' => null, 'effective_to' => null,
        ]));
        $this->vets[] = $vet;
    }
    $groomer = inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, ['groomer'], [$this->q1])->id)->sole());
    inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($groomer, null, [
        'branch_id' => $this->q1->id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'service_group' => 'grooming', 'effective_from' => null, 'effective_to' => null,
    ]));

    $this->book = fn (array $o = []) => inTenant($this->tenant, fn () => app(BookAppointment::class)->handle(new BookingRequest(...[
        'branchId' => $this->q1->id,
        'customerId' => $this->lan->id,
        'items' => [new BookingItem($this->mochi->id, $this->exam->id)],
        'startsAt' => $this->clock->toUtc('2026-11-02', '09:00'),
        'source' => AppointmentSource::Online,
        'idempotencyKey' => (string) Str::uuid(),
        ...$o,
    ])));
    $this->rows = fn (string $model) => inTenant($this->tenant, fn () => $model::query()->count());
});

it('books online: confirmed, lines snapshot the quote, history, quota, event after commit', function () {
    Event::fake([AppointmentBooked::class]);

    $result = ($this->book)(['items' => [new BookingItem($this->mochi->id, $this->exam->id), new BookingItem($this->mochi->id, $this->spa->id)], 'customerNote' => ' Bé sợ máy sấy ']);

    $appointment = $result->appointment->refresh();
    $items = inTenant($this->tenant, fn () => AppointmentItem::query()->orderBy('id')->get());
    expect($appointment->status->value)->toBe('confirmed')
        ->and($appointment->source->value)->toBe('online')
        ->and($appointment->customer_note)->toBe('Bé sợ máy sấy')
        ->and($appointment->starts_at->toIso8601String())->toBe('2026-11-02T02:00:00+00:00')
        ->and($appointment->ends_at->toIso8601String())->toBe('2026-11-02T03:45:00+00:00') // spa 90' (dog rule) + 15' buffer
        ->and($items->map(fn ($i) => [$i->service_group->value, $i->duration_min, $i->reference_price, $i->ends_at->format('H:i'), $i->assigned_staff_id])->all())->toBe([
            ['medical', 30, '200000.00', '02:30', null],
            ['grooming', 90, '350000.00', '03:45', null],
        ])
        ->and(($this->rows)(AppointmentChange::class))->toBe(2)
        ->and(DB::table('tenant_usage')->where('tenant_id', $this->tenant->id)->value('appointments_count'))->toBe(1)
        ->and(DB::table('booking_capacity_locks')->where('tenant_id', $this->tenant->id)->pluck('service_group')->sort()->values()->all())->toBe(['grooming', 'medical'])
        ->and($result->warnings)->toBe([]);

    Event::assertDispatched(AppointmentBooked::class, fn ($e) => $e->appointmentId === $appointment->id && $e->tenantId === $this->tenant->id);
});

it('refuses an online booking once the seat is taken, saving nothing, with the nearest free starts', function () {
    ($this->book)();

    try {
        ($this->book)(['customerId' => $this->binh->id, 'items' => [new BookingItem($this->vang->id, $this->exam->id)]]);
        $this->fail('Expected SlotTakenException');
    } catch (SlotTakenException $e) {
        expect(array_column($e->suggestions, 'time'))->toBe(['08:30', '09:30', '08:15']) // nearest first, ties: earlier first
            ->and($e->suggestions[0]['date'])->toBe('2026-11-02');
    }

    expect(($this->rows)(Appointment::class))->toBe(1)
        ->and(($this->rows)(AppointmentItem::class))->toBe(1)
        ->and(DB::table('tenant_usage')->where('tenant_id', $this->tenant->id)->value('appointments_count'))->toBe(1);
});

it('refuses two pets online into one seat, keeping none of the lines', function () {
    expect(fn () => ($this->book)(['items' => [new BookingItem($this->mochi->id, $this->exam->id), new BookingItem($this->bo->id, $this->exam->id)]]))
        ->toThrow(SlotTakenException::class)
        ->and(($this->rows)(AppointmentItem::class))->toBe(0);
});

it('lets the front desk overbook, with a warning, and front-desk lines leave online seats alone', function () {
    $staff = ['source' => AppointmentSource::Staff, 'createdByStaffId' => $this->vets[0]->id];
    ($this->book)($staff + ['items' => [new BookingItem($this->mochi->id, $this->exam->id), new BookingItem($this->bo->id, $this->exam->id)]]);
    $over = ($this->book)($staff + ['customerId' => $this->binh->id, 'items' => [new BookingItem($this->vang->id, $this->exam->id), new BookingItem($this->vang->id, $this->vaccine->id)]]);

    expect($over->warnings)->toBe(['Vượt số người trực nhóm Khám: vẫn lưu, cần sắp xếp người.'])
        ->and(($this->rows)(AppointmentItem::class))->toBe(4);

    // Online still has its seat: front-desk lines are not counted against it.
    $other = inTenant($this->tenant, fn () => Customer::factory()->has(Pet::factory()->state(['species_id' => $this->dog->id]))->create());
    $otherPet = inTenant($this->tenant, fn () => $other->pets()->value('id'));
    $online = ($this->book)(['customerId' => $other->id, 'items' => [new BookingItem($otherPet, $this->exam->id)]]);
    expect($online->appointment->source->value)->toBe('online');
});

it('warns the front desk when nobody is on duty, and when the assigned person is busy', function () {
    $staff = ['source' => AppointmentSource::Staff];

    $late = ($this->book)($staff + ['startsAt' => $this->clock->toUtc('2026-11-02', '18:00')]);
    expect($late->warnings)->toBe(['Không có ai trực nhóm Khám trong khung này.']);

    ($this->book)($staff + ['customerId' => $this->binh->id, 'items' => [new BookingItem($this->vang->id, $this->exam->id, $this->vets[0]->id)]]);
    $busy = ($this->book)($staff + ['items' => [new BookingItem($this->bo->id, $this->exam->id, $this->vets[0]->id)]]);

    expect($busy->warnings)->toBe([$this->vets[0]->display_name.' đã có lịch khác chồng giờ.']);
});

it('returns the same appointment for the same idempotency key', function () {
    $key = (string) Str::uuid();
    $first = ($this->book)(['idempotencyKey' => $key]);
    $again = ($this->book)(['idempotencyKey' => $key]);

    expect($again->replayed)->toBeTrue()
        ->and($again->appointment->id)->toBe($first->appointment->id)
        ->and(($this->rows)(Appointment::class))->toBe(1)
        ->and(DB::table('tenant_usage')->where('tenant_id', $this->tenant->id)->value('appointments_count'))->toBe(1);
});

it('refuses a pet already booked at an overlapping time, even in another group', function () {
    ($this->book)(['source' => AppointmentSource::Staff]);

    expect(fn () => ($this->book)(['source' => AppointmentSource::Staff, 'items' => [new BookingItem($this->mochi->id, $this->spa->id)], 'startsAt' => $this->clock->toUtc('2026-11-02', '09:15')]))
        ->toThrow(ValidationException::class, 'Bé Mochi đã có lịch khác chồng giờ.');

    // Back to back is fine, and so is another pet.
    ($this->book)(['source' => AppointmentSource::Staff, 'startsAt' => $this->clock->toUtc('2026-11-02', '09:30')]);
    ($this->book)(['source' => AppointmentSource::Staff, 'items' => [new BookingItem($this->bo->id, $this->spa->id)]]);
    expect(($this->rows)(Appointment::class))->toBe(3);
});

it('refuses another customer pet, a service the branch does not offer, closed days and off-grid times', function (Closure $o, string $field) {
    try {
        ($this->book)($o($this));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain($field);
    }
    expect(($this->rows)(Appointment::class))->toBe(0);
})->with([
    'pet of another customer' => [fn ($t) => ['items' => [new BookingItem($t->vang->id, $t->exam->id)]], 'items.0.pet_id'],
    'service not at the branch' => [function ($t) {
        $q3 = inTenant($t->tenant, fn () => Branch::factory()->create(['code' => 'Q3']));
        inTenant($t->tenant, fn () => $t->exam->branches()->sync([$q3->id]));

        return [];
    }, 'items.0.service_id'],
    'closed day' => [function ($t) {
        inTenant($t->tenant, fn () => BranchClosure::query()->create(['branch_id' => $t->q1->id, 'date' => '2026-11-02']));

        return ['source' => AppointmentSource::Staff];
    }, 'starts_at'],
    'before opening' => [fn ($t) => ['source' => AppointmentSource::Staff, 'startsAt' => $t->clock->toUtc('2026-11-02', '07:30')], 'starts_at'],
    'off the 15-minute grid' => [fn ($t) => ['source' => AppointmentSource::Staff, 'startsAt' => $t->clock->toUtc('2026-11-02', '09:10')], 'starts_at'],
    'online inside the lead time' => [fn ($t) => ['startsAt' => CarbonImmutable::now()->addMinutes(30)], 'starts_at'],
    'online with an assigned person' => [fn ($t) => ['items' => [new BookingItem($t->mochi->id, $t->exam->id, $t->vets[0]->id)]], 'items.0.assigned_staff_id'],
]);

it('stops at the plan quota, counting appointments not lines, and keeps counting on unlimited plans', function () {
    $this->plan->update(['max_appointments_per_month' => 1]);
    ($this->book)(['source' => AppointmentSource::Staff, 'items' => [new BookingItem($this->mochi->id, $this->exam->id), new BookingItem($this->bo->id, $this->exam->id)]]);

    expect(fn () => ($this->book)(['source' => AppointmentSource::Staff, 'customerId' => $this->binh->id, 'items' => [new BookingItem($this->vang->id, $this->exam->id)]]))
        ->toThrow(QuotaExceededException::class)
        ->and(($this->rows)(Appointment::class))->toBe(1);

    $this->plan->update(['max_appointments_per_month' => null]);
    ($this->book)(['source' => AppointmentSource::Staff, 'customerId' => $this->binh->id, 'items' => [new BookingItem($this->vang->id, $this->exam->id)]]);
    expect(DB::table('tenant_usage')->where('tenant_id', $this->tenant->id)->value('appointments_count'))->toBe(2);
});

it('renders a taken slot as HTTP 409 with suggestions', function () {
    $response = (new SlotTakenException)->withSuggestions([['date' => '2026-11-02', 'time' => '09:30', 'starts_at' => '2026-11-02T02:30:00Z']])
        ->render(request()->duplicate(server: ['HTTP_ACCEPT' => 'application/json']));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true))->toMatchArray(['code' => 'slot_taken', 'suggestions' => [['date' => '2026-11-02', 'time' => '09:30', 'starts_at' => '2026-11-02T02:30:00Z']]]);
});
