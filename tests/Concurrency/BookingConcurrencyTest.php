<?php

declare(strict_types=1);

use App\Actions\Appointments\BookAppointment;
use App\Actions\Rosters\SaveWorkingHour;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Enums\AppointmentSource;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\OnlineBookingRate;
use App\Models\Pet;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Species;
use App\Models\Staff;
use App\Scheduling\ShopClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/*
| Task 5.5 / ADR 0002: real races. Every attempt is its own PHP process with its own MySQL
| connection, released together by a time barrier. Data is committed (DatabaseTruncation), never
| wrapped in a test transaction. Runs on MySQL only.
|
| 3 vets 08:00–17:00 at 1/3 = 1 online exam seat; 1 groomer at 1/1 = 1 online spa seat.
| The booking day is far ahead (lead time never matters) and a Monday.
*/

const BOOKING_DAY = '2027-03-01';

beforeEach(function () {
    $this->plan = Plan::factory()->create(['max_staff' => 50, 'max_appointments_per_month' => null]);
    $this->shop = provisionShop(['plan' => $this->plan]);
    $this->tenant = $this->shop->tenant;
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->clock = ShopClock::for($this->tenant);
    $dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);

    inTenant($this->tenant, function () use ($dog) {
        $this->exam = Service::factory()->create(['code' => 'KHAM', 'service_group' => ServiceGroup::Medical, 'duration_min' => 30, 'buffer_min' => 0]);
        $this->spa = Service::factory()->create(['code' => 'SPA', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 30, 'buffer_min' => 0]);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Medical, 'ratio' => '0.333']);
        OnlineBookingRate::factory()->create(['branch_id' => $this->q1->id, 'service_group' => ServiceGroup::Grooming, 'ratio' => '1.000']);
        $this->customers = Customer::factory()->count(20)->has(Pet::factory()->count(2)->state(['species_id' => $dog->id]))->create()->load('pets');
    });

    $slot = fn (Staff $s, string $group) => inTenant($this->tenant, fn () => app(SaveWorkingHour::class)->handle($s, null, [
        'branch_id' => $this->q1->id, 'weekday' => 1, 'start_time' => '08:00', 'end_time' => '17:00', 'service_group' => $group, 'effective_from' => null, 'effective_to' => null,
    ]));
    foreach ([['vet'], ['vet'], ['vet'], ['groomer']] as $roles) {
        $staff = inTenant($this->tenant, fn () => Staff::query()->where('user_id', memberWithRoles($this->tenant, $roles, [$this->q1])->id)->sole());
        $slot($staff, $roles === ['vet'] ? 'medical' : 'grooming');
    }

    $this->at = fn (string $time) => $this->clock->toUtc(BOOKING_DAY, $time)->toIso8601String();
    $this->payload = fn (Customer $c, array $o = []) => [
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->q1->id, 'customer_id' => $c->id,
        'items' => [[$c->pets[0]->id, $this->exam->id]], 'starts_at' => ($this->at)('09:00'), 'source' => 'online',
        'idempotency_key' => (string) Str::uuid(), ...$o,
    ];
});

/**
 * Starts every attempt at once and returns their JSON results.
 *
 * @param  list<array<string, mixed>>  $payloads
 * @return list<array{ok: bool, error?: string, message?: string, appointment?: int, replayed?: bool, ms: int}>
 */
function race(array $payloads): array
{
    $at = microtime(true) + 4 + count($payloads) * 0.1; // time for every process to boot
    $script = base_path('tests/Concurrency/Support/book.php');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) config('database.connections.mysql.host'),
        'DB_PORT' => (string) config('database.connections.mysql.port'),
        'DB_DATABASE' => (string) config('database.connections.mysql.database'),
        'DB_USERNAME' => (string) config('database.connections.mysql.username'),
        'DB_PASSWORD' => (string) config('database.connections.mysql.password'),
        'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
    ];

    $deadlocks = deadlocks();
    $processes = array_map(function (array $payload) use ($script, $env, $at): Process {
        $process = new Process([PHP_BINARY, $script, json_encode([...$payload, 'at' => $at])], base_path(), $env, timeout: 60);
        $process->start();

        return $process;
    }, $payloads);

    $results = array_map(function (Process $p): array {
        $p->wait();
        $line = trim($p->getOutput());

        return json_decode($line, true) ?? ['ok' => false, 'error' => 'crash', 'message' => $line.' '.$p->getErrorOutput(), 'ms' => 0];
    }, $processes);

    // CONCURRENCY_REPORT=1: per-race numbers for ADR 0002 (time per attempt, deadlocks retried).
    if (getenv('CONCURRENCY_REPORT')) {
        $ms = array_column($results, 'ms');
        sort($ms);
        $after = deadlocks();
        fwrite(STDERR, sprintf(
            "\n[race] %s | %d attempts | ms avg %d, p50 %d, max %d | deadlocks %s\n",
            json_encode(outcomes($results)), count($ms), (int) (array_sum($ms) / max(1, count($ms))), $ms[intdiv(count($ms), 2)] ?? 0, end($ms) ?: 0,
            $deadlocks === null || $after === null ? 'n/a' : (string) ($after - $deadlocks),
        ));
    }

    return $results;
}

/** InnoDB's deadlock counter, when the DB user may read it. */
function deadlocks(): ?int
{
    try {
        $value = DB::table('information_schema.INNODB_METRICS')->where('NAME', 'lock_deadlocks')->value('COUNT');

        return $value === null ? null : (int) $value;
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param  list<array<string, mixed>>  $results
 * @return array<string, int>
 */
function outcomes(array $results): array
{
    $counts = array_count_values(array_map(fn (array $r) => $r['ok'] ? (($r['replayed'] ?? false) ? 'replayed' : 'ok') : $r['error'], $results));
    ksort($counts);

    return $counts;
}

it('lets exactly one of 20 online bookings take the last seat', function () {
    $results = race(array_map(fn (Customer $c) => ($this->payload)($c), $this->customers->all()));

    expect(outcomes($results))->toBe(['ok' => 1, 'slot_taken' => 19])
        ->and(inTenant($this->tenant, fn () => AppointmentItem::query()->count()))->toBe(1);
})->group('mysql', 'concurrency');

it('refuses two-pet bookings into a one-seat slot without saving any line', function () {
    $results = race(array_map(fn (Customer $c) => ($this->payload)($c, ['items' => [[$c->pets[0]->id, $this->exam->id], [$c->pets[1]->id, $this->exam->id]]]), $this->customers->take(10)->all()));

    expect(outcomes($results))->toBe(['slot_taken' => 10])
        ->and(inTenant($this->tenant, fn () => AppointmentItem::query()->count()))->toBe(0);
})->group('mysql', 'concurrency');

it('hands out the last plan appointment once among 10 requests', function () {
    $this->plan->update(['max_appointments_per_month' => 5]);
    DB::table('tenant_usage')->insert(['tenant_id' => $this->tenant->id, 'period' => $this->clock->today()->format('Y-m'), 'appointments_count' => 4]);

    // Front desk, different times: only the quota can refuse.
    $results = race(array_map(fn (Customer $c, int $i) => ($this->payload)($c, ['source' => 'staff', 'starts_at' => ($this->at)(sprintf('%02d:00', 8 + $i % 8))]), $this->customers->take(10)->all(), range(0, 9)));

    expect(outcomes($results))->toBe(['ok' => 1, 'quota' => 9])
        ->and(DB::table('tenant_usage')->where('tenant_id', $this->tenant->id)->value('appointments_count'))->toBe(5);
})->group('mysql', 'concurrency');

it('creates one appointment for 10 requests with the same idempotency key', function () {
    $payload = ($this->payload)($this->customers[0], ['idempotency_key' => (string) Str::uuid()]);

    $results = race(array_fill(0, 10, $payload));

    expect(outcomes($results))->toBe(['ok' => 1, 'replayed' => 9])
        ->and(collect($results)->pluck('appointment')->unique()->count())->toBe(1)
        ->and(inTenant($this->tenant, fn () => Appointment::query()->count()))->toBe(1);
})->group('mysql', 'concurrency');

it('does not deadlock exam + spa against spa + exam bookings', function () {
    $results = race(array_map(fn (Customer $c, int $i) => ($this->payload)($c, [
        'source' => 'staff',
        'items' => $i % 2 === 0
            ? [[$c->pets[0]->id, $this->exam->id], [$c->pets[1]->id, $this->spa->id]]
            : [[$c->pets[0]->id, $this->spa->id], [$c->pets[1]->id, $this->exam->id]],
    ]), $this->customers->all(), range(0, 19)));

    expect(outcomes($results))->toBe(['ok' => 20])
        ->and(inTenant($this->tenant, fn () => AppointmentItem::query()->count()))->toBe(40);
})->group('mysql', 'concurrency');

it('does not deadlock when 20 requests create the day lock rows together', function () {
    expect(DB::table('booking_capacity_locks')->count())->toBe(0);

    $results = race(array_map(fn (Customer $c) => ($this->payload)($c, ['items' => [[$c->pets[0]->id, $this->exam->id], [$c->pets[1]->id, $this->spa->id]], 'starts_at' => ($this->at)('10:00')]), $this->customers->all()));

    expect(outcomes($results))->toBe(['ok' => 1, 'slot_taken' => 19])
        ->and(DB::table('booking_capacity_locks')->count())->toBe(2);
})->group('mysql', 'concurrency');

it('keeps one pet out of two overlapping front-desk bookings sent together', function () {
    $c = $this->customers[0];
    $pet = $c->pets[0]->id;

    $results = race([
        ($this->payload)($c, ['source' => 'staff', 'items' => [[$pet, $this->exam->id]]]),
        ($this->payload)($c, ['source' => 'staff', 'items' => [[$pet, $this->spa->id]], 'starts_at' => ($this->at)('09:15')]),
    ]);

    expect(outcomes($results))->toBe(['invalid' => 1, 'ok' => 1])
        ->and(collect($results)->firstWhere('ok', false)['message'] ?? null)->toContain('đã có lịch khác chồng giờ');
})->group('mysql', 'concurrency');

it('does not deadlock reschedules crossing two days in opposite directions', function () {
    // 10 appointments on each day, exam + spa (two lock rows per day), booked by the front desk.
    $next = '2027-03-08'; // also a Monday
    $book = fn (Customer $c, string $day) => inTenant($this->tenant, fn () => app(BookAppointment::class)->handle(new BookingRequest(
        branchId: $this->q1->id, customerId: $c->id,
        items: [new BookingItem($c->pets[0]->id, $this->exam->id), new BookingItem($c->pets[1]->id, $this->spa->id)],
        startsAt: $this->clock->toUtc($day, '10:00'), source: AppointmentSource::Staff,
    ))->appointment);
    $there = $this->customers->take(10)->map(fn (Customer $c) => $book($c, BOOKING_DAY))->all();
    $back = $this->customers->slice(10)->map(fn (Customer $c) => $book($c, $next))->all();

    $results = race([
        ...array_map(fn ($a) => ['action' => 'reschedule', 'tenant_id' => $this->tenant->id, 'appointment_id' => $a->id, 'starts_at' => $this->clock->toUtc($next, '14:00')->toIso8601String(), 'source' => 'staff'], $there),
        ...array_map(fn ($a) => ['action' => 'reschedule', 'tenant_id' => $this->tenant->id, 'appointment_id' => $a->id, 'starts_at' => $this->clock->toUtc(BOOKING_DAY, '14:00')->toIso8601String(), 'source' => 'staff'], $back),
    ]);

    expect(outcomes($results))->toBe(['ok' => 20])
        ->and(inTenant($this->tenant, fn () => Appointment::query()->get()->map(fn ($a) => $this->clock->toLocal($a->starts_at)->format('Y-m-d H:i'))->countBy()->sortKeys()->all()))
        ->toBe([BOOKING_DAY.' 14:00' => 10, $next.' 14:00' => 10]);
})->group('mysql', 'concurrency');
