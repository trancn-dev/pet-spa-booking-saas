<?php

declare(strict_types=1);

/*
| One booking attempt in its own PHP process, with its own MySQL connection (task 5.5).
| Usage: php book.php '<json payload>' (`action`: book (default) or reschedule). Prints one JSON line: {ok, error?, appointment?, replayed?, ms}.
| The payload's `at` (unix time, float) is a start barrier so every process fires together.
*/

use App\Actions\Appointments\BookAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Booking\Exceptions\QuotaExceededException;
use App\Booking\Exceptions\SlotTakenException;
use App\Enums\ActorType;
use App\Enums\AppointmentSource;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var array{tenant_id: int, branch_id: int, customer_id: int, items: list<array{0: int, 1: int, 2?: int|null}>, starts_at: string, source: string, idempotency_key?: string|null, at: float} $payload */
$payload = json_decode($argv[1] ?? '{}', true, flags: JSON_THROW_ON_ERROR);

DB::select('SELECT 1'); // connect before the barrier
$tenant = Tenant::query()->findOrFail($payload['tenant_id']);
while (microtime(true) < $payload['at']) {
    usleep(200);
}

$started = microtime(true);
$out = static fn (array $data): never => exit(json_encode([...$data, 'ms' => (int) round((microtime(true) - $started) * 1000)]).PHP_EOL);

try {
    $result = app(TenantContext::class)->runAs($tenant, fn () => ($payload['action'] ?? 'book') === 'reschedule'
        ? app(RescheduleAppointment::class)->handle(
            Appointment::query()->findOrFail($payload['appointment_id']),
            CarbonImmutable::parse($payload['starts_at']),
            $payload['source'] === 'online' ? ActorType::Customer : ActorType::Staff,
        )
        : app(BookAppointment::class)->handle(new BookingRequest(
            branchId: $payload['branch_id'],
            customerId: $payload['customer_id'],
            items: array_map(fn (array $i) => new BookingItem($i[0], $i[1], $i[2] ?? null), $payload['items']),
            startsAt: CarbonImmutable::parse($payload['starts_at']),
            source: AppointmentSource::from($payload['source']),
            idempotencyKey: $payload['idempotency_key'] ?? null,
        )));
    $out(['ok' => true, 'appointment' => $result->appointment->id, 'replayed' => $result->replayed]);
} catch (SlotTakenException) {
    $out(['ok' => false, 'error' => 'slot_taken']);
} catch (QuotaExceededException) {
    $out(['ok' => false, 'error' => 'quota']);
} catch (ValidationException $e) {
    $out(['ok' => false, 'error' => 'invalid', 'message' => collect($e->errors())->flatten()->first()]);
} catch (Throwable $e) {
    $out(['ok' => false, 'error' => 'exception', 'message' => $e::class.': '.$e->getMessage()]);
}
