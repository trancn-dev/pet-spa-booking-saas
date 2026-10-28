<?php

declare(strict_types=1);

use App\Actions\Appointments\AddAppointmentItems;
use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CheckInAppointment;
use App\Actions\Appointments\MarkNoShow;
use App\Actions\Appointments\RescheduleAppointment;
use App\Actions\Appointments\RestoreCancellation;
use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Rosters\SaveWorkingHour;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Booking\Exceptions\SlotTakenException;
use App\Enums\ActorType;
use App\Enums\AppointmentSource;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\OnlineBookingRate;
use App\Models\Pet;
use App\Models\Plan;
use App\Models\Reminder;
use App\Models\Service;
use App\Models\Species;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
| Task 5.6: line state machine, check-in → Visits, cancel / undo, no-show, reschedule, add lines.
| Shop in Asia/Ho_Chi_Minh. "Now" is Monday 2026-11-02 08:30 local; 1 vet on duty 08:00–17:00 at
| ratio 1 (one online exam seat), 1 groomer at ratio 1.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-02T01:30:00Z'));
    $this->shop = provisionShop(['plan' => Plan::factory()->create(['max_staff' => 50])]);
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->clock = ShopClock::for($this->tenant);
    $dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);

    inTenant($this->tenant, function () use ($dog) {
        $this->exam = Service::factory()->create(['code' => 'KHAM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'buffer_min' => 0]);
        $this->vaccine = Service::factory()->create(['code' => 'TIEM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 15, 'buffer_min' => 0]);
        $this->spa = Service::factory()->create(['code' => 'SPA', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 60, 'buffer_min' => 0]);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Medical, 'ratio' => '1.000']);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Grooming, 'ratio' => '1.000']);
        $this->lan = Customer::factory()->create();
        $this->mochi = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $dog->id, 'name' => 'Mochi']);
        $this->bo = Pet::factory()->create(['customer_id' => $this->lan->id, 'species_id' => $dog->id, 'name' => 'Bơ']);
        $this->binh = Customer::factory()->create();
        $this->vang = Pet::factory()->create(['customer_id' => $this->binh->id, 'species_id' => $dog->id, 'name' => 'Vàng']);
    });

    $onDuty = function (array $roles, string $group): Staff {
        $staff = inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, $roles, [$this->q1])->id)->sole());
        foreach ([1, 2, 3] as $weekday) {
            inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($staff, null, [
                'branch_id' => $this->q1->id, 'weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '17:00', 'service_group' => $group, 'effective_from' => null, 'effective_to' => null,
            ]));
        }

        return $staff;
    };
    $this->vet = $onDuty(['vet'], 'medical');
    $this->groomer = $onDuty(['groomer'], 'grooming');

    $this->at = fn (string $time, string $day = '2026-11-02') => $this->clock->toUtc($day, $time);
    $this->book = fn (array $items, string $time = '09:00', array $o = []) => inTenant($this->tenant, fn () => app(BookAppointment::class)->handle(new BookingRequest(...[
        'branchId' => $this->q1->id, 'customerId' => $this->lan->id,
        'items' => array_map(fn (array $i) => new BookingItem(...$i), $items),
        'startsAt' => ($this->at)($time), 'source' => AppointmentSource::Staff, 'idempotencyKey' => (string) Str::uuid(), ...$o,
    ]))->appointment);
    $this->run = fn (string $class, string $method = 'handle', mixed ...$args) => inTenant($this->tenant, fn () => app($class)->{$method}(...$args));
    $this->lines = fn (Appointment $a) => inTenant($this->tenant, fn () => AppointmentItem::query()->where('appointment_id', $a->id)->orderBy('id')->get());
    $this->history = fn (Appointment $a) => inTenant($this->tenant, fn () => AppointmentChange::query()->where('appointment_id', $a->id)->orderBy('id')->get()->map(fn ($c) => $c->action->value)->all());
});

it('checks in pets: same pet and group share one Visit, each Visit gets its queue number', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->mochi->id, $this->vaccine->id], [$this->mochi->id, $this->spa->id], [$this->bo->id, $this->exam->id]]);
    [$exam, $vaccine, $spa, $boExam] = ($this->lines)($a)->all();

    $this->travelTo(($this->at)('09:05'));
    $visits = ($this->run)(CheckInAppointment::class, 'handle', $a, [$exam->id, $vaccine->id, $spa->id], [$exam->id => $this->vet->id]);

    $lines = ($this->lines)($a);
    expect(collect($visits)->map(fn (Visit $v) => [$v->queue_no, $v->service_group->value, $v->pet_id, $v->status->value])->all())->toBe([
        [1, 'medical', $this->mochi->id, 'waiting'],
        [2, 'grooming', $this->mochi->id, 'waiting'],
    ])
        ->and($visits[0]->priority_at->toIso8601String())->toBe(($this->at)('09:00')->toIso8601String()) // on time: keeps the booked time
        ->and($visits[0]->assigned_staff_id)->toBe($this->vet->id)
        ->and($lines[0]->visit_id)->toBe($lines[1]->visit_id)
        ->and($lines->pluck('status')->map->value->all())->toBe(['checked_in', 'checked_in', 'checked_in', 'booked'])
        ->and($a->refresh()->status->value)->toBe('checked_in');

    // Bơ arrives later: same appointment, a Visit of its own; Mochi's lines are left alone.
    $this->travelTo(($this->at)('09:40'));
    $more = ($this->run)(CheckInAppointment::class, 'handle', $a, [$boExam->id, $exam->id]);
    expect(collect($more)->pluck('queue_no')->all())->toBe([1, 3])
        ->and(collect($more)->firstWhere('queue_no', 3)->priority_at->toIso8601String())->toBe(($this->at)('09:40')->toIso8601String()) // late: walk-in time
        ->and(inTenant($this->tenant, fn () => Visit::query()->count()))->toBe(3);
});

it('checks in twice without opening a second Visit', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id]]);
    $line = ($this->lines)($a)->first();

    ($this->run)(CheckInAppointment::class, 'handle', $a, [$line->id]);
    ($this->run)(CheckInAppointment::class, 'handle', $a, [$line->id]);

    expect(inTenant($this->tenant, fn () => Visit::query()->count()))->toBe(1)
        ->and(array_count_values(($this->history)($a))['checked_in'])->toBe(1);
});

it('refuses invalid moves: check-in another day, cancelled or no-show lines, cancel after check-in', function () {
    $tomorrow = ($this->book)([[$this->mochi->id, $this->exam->id]], '09:00', ['startsAt' => ($this->at)('09:00', '2026-11-03')]);
    expect(fn () => ($this->run)(CheckInAppointment::class, 'handle', $tomorrow, [($this->lines)($tomorrow)->first()->id]))
        ->toThrow(ValidationException::class, 'Chỉ check-in trong ngày hẹn');

    $a = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->bo->id, $this->exam->id]]);
    [$m, $b] = ($this->lines)($a)->all();
    ($this->run)(CancelAppointment::class, 'handle', $a, [$b->id], 'Bé ốm', ActorType::Staff);
    expect(fn () => ($this->run)(CheckInAppointment::class, 'handle', $a, [$b->id]))->toThrow(ValidationException::class, 'Dòng đã hủy không chuyển sang đã đến được.');

    ($this->run)(CheckInAppointment::class, 'handle', $a, [$m->id]);
    expect(fn () => ($this->run)(CancelAppointment::class, 'handle', $a, [$m->id], 'x', ActorType::Staff))->toThrow(ValidationException::class, 'Dòng đã đến không chuyển sang đã hủy được.')
        ->and(fn () => ($this->run)(CancelAppointment::class, 'handle', $a, null, null, ActorType::Staff))->toThrow(ValidationException::class, 'lý do');
});

it('derives the appointment status from its lines and skips reminders on cancel', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->bo->id, $this->exam->id]]);
    [$m, $b] = ($this->lines)($a)->all();
    $reminder = inTenant($this->tenant, fn () => Reminder::factory()->create(['remindable_type' => 'appointment', 'remindable_id' => $a->id, 'customer_id' => $this->lan->id]));

    ($this->run)(CancelAppointment::class, 'handle', $a, [$b->id], 'Bé ốm', ActorType::Staff);
    expect($a->refresh()->status->value)->toBe('confirmed')->and($reminder->refresh()->status->value)->toBe('pending');

    ($this->run)(CancelAppointment::class, 'handle', $a, null, 'Khách bận', ActorType::Staff, $this->vet->id);
    $a->refresh();
    expect($a->status->value)->toBe('cancelled')
        ->and($a->cancel_reason)->toBe('Khách bận')
        ->and($a->cancelled_by_type?->value)->toBe('staff')
        ->and($reminder->refresh()->status->value)->toBe('skipped')
        ->and(($this->history)($a))->toBe(['item_added', 'item_added', 'cancelled', 'cancelled', 'cancelled']);

    // One line cancelled, the other not shown up: no_show.
    $c = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->bo->id, $this->exam->id]], '10:00');
    [$cm, $cb] = ($this->lines)($c)->all();
    ($this->run)(CancelAppointment::class, 'handle', $c, [$cb->id], 'Bé ốm', ActorType::Staff);
    $this->travelTo(($this->at)('10:30'));
    ($this->run)(MarkNoShow::class, 'handle', $c, null, ActorType::Staff);
    expect($c->refresh()->status->value)->toBe('no_show')->and($c->no_show_at)->not->toBeNull();
});

it('lets a customer cancel online only before the cutoff', function () {
    $soon = ($this->book)([[$this->mochi->id, $this->exam->id]], '15:00');

    expect(fn () => ($this->run)(CancelAppointment::class, 'handle', $soon, null, null, ActorType::Customer))->toThrow(ValidationException::class, '24 giờ');

    $later = ($this->book)([[$this->mochi->id, $this->exam->id]], '09:00', ['startsAt' => ($this->at)('09:00', '2026-11-04')]);
    ($this->run)(CancelAppointment::class, 'handle', $later, null, null, ActorType::Customer);
    expect($later->refresh()->status->value)->toBe('cancelled');
});

it('undoes a cancellation within the window, through the capacity check again', function () {
    $mine = ($this->book)([[$this->mochi->id, $this->exam->id]], '11:00', ['source' => AppointmentSource::Online]);
    ($this->run)(CancelAppointment::class, 'handle', $mine, null, 'Khách gọi hủy', ActorType::Staff);

    ($this->run)(RestoreCancellation::class, 'handle', $mine, ActorType::Staff);
    expect($mine->refresh()->status->value)->toBe('confirmed')
        ->and(($this->lines)($mine)->first()->status->value)->toBe('booked')
        ->and(array_slice(($this->history)($mine), -1))->toBe(['restored']);

    // Cancelled again; meanwhile someone takes the only online seat; the customer's undo is refused.
    ($this->run)(CancelAppointment::class, 'handle', $mine, null, 'Nhầm', ActorType::Staff);
    ($this->book)([[$this->vang->id, $this->exam->id]], '11:00', ['customerId' => $this->binh->id, 'source' => AppointmentSource::Online]);
    expect(fn () => ($this->run)(RestoreCancellation::class, 'handle', $mine, ActorType::Customer))->toThrow(SlotTakenException::class);

    $this->travel(6)->seconds();
    expect(fn () => ($this->run)(RestoreCancellation::class, 'handle', $mine, ActorType::Staff))->toThrow(ValidationException::class, 'quá thời gian hoàn tác');
});

it('reschedules every booked line, keeps the history and skips pending reminders', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->bo->id, $this->spa->id]]);
    $reminder = inTenant($this->tenant, fn () => Reminder::factory()->create(['remindable_type' => 'appointment', 'remindable_id' => $a->id, 'customer_id' => $this->lan->id]));

    ($this->run)(RescheduleAppointment::class, 'handle', $a, ($this->at)('14:00', '2026-11-03'), ActorType::Staff, $this->vet->id, 'Khách xin dời');

    $a->refresh();
    $lines = ($this->lines)($a);
    $change = inTenant($this->tenant, fn () => AppointmentChange::query()->where('action', 'rescheduled')->sole());
    expect($a->starts_at->toIso8601String())->toBe(($this->at)('14:00', '2026-11-03')->toIso8601String())
        ->and($a->ends_at->toIso8601String())->toBe(($this->at)('15:00', '2026-11-03')->toIso8601String())
        ->and($lines->map(fn ($l) => [$l->starts_at->format('H:i'), $l->ends_at->format('H:i')])->all())->toBe([['07:00', '07:30'], ['07:00', '08:00']])
        ->and($change->old_starts_at->toIso8601String())->toBe(($this->at)('09:00')->toIso8601String())
        ->and($change->new_starts_at->toIso8601String())->toBe(($this->at)('14:00', '2026-11-03')->toIso8601String())
        ->and($change->reason)->toBe('Khách xin dời')
        ->and($reminder->refresh()->status->value)->toBe('skipped');
});

it('refuses an online reschedule into a full slot, but never counts the appointment against itself', function () {
    $mine = ($this->book)([[$this->mochi->id, $this->exam->id]], '11:00', ['source' => AppointmentSource::Online, 'startsAt' => ($this->at)('11:00', '2026-11-04')]);
    ($this->book)([[$this->vang->id, $this->exam->id]], '14:00', ['customerId' => $this->binh->id, 'source' => AppointmentSource::Online, 'startsAt' => ($this->at)('14:00', '2026-11-04')]);

    // 15 minutes later overlaps its own old time only: fine.
    ($this->run)(RescheduleAppointment::class, 'handle', $mine, ($this->at)('11:15', '2026-11-04'), ActorType::Customer);
    expect($mine->refresh()->starts_at->toIso8601String())->toBe(($this->at)('11:15', '2026-11-04')->toIso8601String());

    try {
        ($this->run)(RescheduleAppointment::class, 'handle', $mine, ($this->at)('14:00', '2026-11-04'), ActorType::Customer);
        $this->fail('Expected SlotTakenException');
    } catch (SlotTakenException $e) {
        expect($e->suggestions)->not->toBeEmpty();
    }

    // The front desk may still move it there, with a warning.
    $result = ($this->run)(RescheduleAppointment::class, 'handle', $mine, ($this->at)('14:00', '2026-11-04'), ActorType::Staff);
    expect($result->warnings)->toBe(['Vượt số người trực nhóm Khám: vẫn lưu, cần sắp xếp người.']);
});

it('adds lines through the capacity check and books a cancelled line again', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id], [$this->bo->id, $this->exam->id]], '11:00', ['startsAt' => ($this->at)('11:00', '2026-11-04')]);
    [, $b] = ($this->lines)($a)->all();

    ($this->run)(AddAppointmentItems::class, 'handle', $a, [new BookingItem($this->mochi->id, $this->spa->id)], ActorType::Staff);
    expect(($this->lines)($a)->count())->toBe(3)
        ->and($a->refresh()->ends_at->toIso8601String())->toBe(($this->at)('12:00', '2026-11-04')->toIso8601String());

    expect(fn () => ($this->run)(AddAppointmentItems::class, 'handle', $a, [new BookingItem($this->mochi->id, $this->spa->id)], ActorType::Staff))
        ->toThrow(ValidationException::class, 'Bé Mochi đã có');

    ($this->run)(CancelAppointment::class, 'handle', $a, [$b->id], 'Bé ốm', ActorType::Staff, null, false, 'item_removed');
    ($this->run)(AddAppointmentItems::class, 'handle', $a, [new BookingItem($this->bo->id, $this->exam->id)], ActorType::Staff);
    expect($b->refresh()->status->value)->toBe('booked')
        ->and(array_slice(($this->history)($a), -2))->toBe(['item_removed', 'item_added']);
});

it('marks yesterday’s booked lines no-show at the end of the shop day, once', function () {
    $this->travelTo(($this->at)('08:00', '2026-11-02'));
    $old = ($this->book)([[$this->mochi->id, $this->exam->id]], '09:00');
    $today = ($this->book)([[$this->vang->id, $this->exam->id]], '09:00', ['customerId' => $this->binh->id, 'startsAt' => ($this->at)('09:00', '2026-11-03')]);

    $this->travelTo(($this->at)('00:15', '2026-11-03'));
    $this->artisan('appointments:mark-no-show')->expectsOutput('Marked 1 line(s) as no-show.')->assertSuccessful();
    $this->artisan('appointments:mark-no-show')->expectsOutput('Marked 0 line(s) as no-show.')->assertSuccessful();

    expect($old->refresh()->status->value)->toBe('no_show')
        ->and($today->refresh()->status->value)->toBe('confirmed')
        ->and(($this->history)($old))->toBe(['item_added', 'no_show']);
});

it('lets the front desk act over HTTP, by role', function () {
    $a = ($this->book)([[$this->mochi->id, $this->exam->id]]);
    $line = ($this->lines)($a)->first();
    $receptionist = memberWithRoles($this->tenant, ['receptionist'], [$this->q1]);
    $groomer = memberWithRoles($this->tenant, ['groomer'], [$this->q1]);

    $this->actingAs($groomer)->postJson("/app/paw-test/appointments/{$a->public_id}/cancel", ['reason' => 'x'])->assertForbidden();
    $this->actingAs($groomer)->postJson("/app/paw-test/appointments/{$a->public_id}/check-in", ['items' => [$line->id]])->assertForbidden();

    $this->actingAs($receptionist)->postJson("/app/paw-test/appointments/{$a->public_id}/reschedule", ['date' => '2026-11-02', 'time' => '10:00'])
        ->assertOk()->assertJsonPath('starts_at', '2026-11-02T03:00:00Z');
    $this->actingAs($receptionist)->postJson("/app/paw-test/appointments/{$a->public_id}/check-in", ['items' => [$line->id]])
        ->assertOk()->assertJsonPath('visits.0.queue_no', 1);

    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);
    $foreign = inTenant($other->tenant, fn () => Appointment::factory()->create());
    assertTenantIsolated($this->owner, [
        "POST /app/paw-test/appointments/{$foreign->public_id}/cancel",
        "POST /app/paw-test/appointments/{$foreign->public_id}/check-in",
        "POST /app/paw-test/appointments/{$foreign->public_id}/reschedule",
    ], ['reason' => 'x', 'items' => [1], 'date' => '2026-11-02', 'time' => '10:00']);
    // a line of another appointment cannot be smuggled in
    $b = ($this->book)([[$this->vang->id, $this->exam->id]], '12:00', ['customerId' => $this->binh->id]);
    $this->actingAs($this->owner)->postJson("/app/paw-test/appointments/{$b->public_id}/cancel", ['reason' => 'x', 'items' => [$line->id]])->assertNotFound();
});
