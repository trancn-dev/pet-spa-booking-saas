<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\BookingGate;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Booking\BookingResult;
use App\Booking\Exceptions\QuotaExceededException;
use App\Booking\Exceptions\SlotTakenException;
use App\Booking\PlannedLine;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Pet;
use App\Models\Service;
use App\Pricing\ServiceQuoteResolver;
use App\Scheduling\ShopClock;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books an appointment without ever going over the online capacity (db-schema 5.1, ADR 0002).
 * Same path for the Site and the front desk; the locking and capacity steps live in BookingGate
 * (shared with reschedule, add line, restore).
 *
 * 0. lock rows created outside the transaction; then, in one READ COMMITTED transaction retried
 *    on deadlock: 2. customer row → 1. idempotency key → pets / services → 3. capacity rows →
 *    4/5. capacity (online refused, front desk warned) → 6. pets free → 7. plan quota (last lock)
 *    → 8. appointment, lines, history; AppointmentBooked after commit.
 */
class BookAppointment
{
    public const ATTEMPTS = 3;

    public function __construct(
        private readonly TenantContext $context,
        private readonly ServiceQuoteResolver $quotes,
    ) {}

    public function handle(BookingRequest $request): BookingResult
    {
        $tenant = $this->context->require();
        $gate = BookingGate::forCurrentShop();
        $online = $request->source === AppointmentSource::Online;

        if ($request->items === []) {
            throw ValidationException::withMessages(['items' => 'Chọn ít nhất một dịch vụ cho một bé.']);
        }

        $branch = Branch::query()->whereKey($request->branchId)->where('is_active', true)->first()
            ?? throw ValidationException::withMessages(['branch_id' => 'Cơ sở không hợp lệ.']);
        $lines = $this->lines($request->items, $online);
        $start = $request->startsAt->utc();
        if ($online) {
            BookingGate::ensureLeadTime($start);
        }

        $keys = $gate->keys($lines, $start);
        $gate->prepare((int) $tenant->getKey(), $branch->id, $keys);

        try {
            $result = DB::transaction(fn (): BookingResult => $this->book($gate, $request, $branch, $lines, $keys, $start), self::ATTEMPTS);
        } catch (SlotTakenException $e) {
            throw $e->withSuggestions($gate->suggestions($branch, $lines, $start));
        } catch (UniqueConstraintViolationException $e) {
            // Same idempotency key from another customer's request that won the race.
            $existing = $request->idempotencyKey === null ? null : Appointment::query()->where('idempotency_key', $request->idempotencyKey)->first();

            return $existing === null ? throw $e : new BookingResult($existing, replayed: true);
        }

        if (! $result->replayed) {
            $tenantId = (int) $tenant->getKey();
            $appointmentId = $result->appointment->id;
            DB::afterCommit(fn () => AppointmentBooked::dispatch($tenantId, $appointmentId));
        }

        return $result;
    }

    /**
     * Services, pets and quotes for the requested items (plain reads: layout only, never capacity).
     *
     * @param  list<BookingItem>  $items
     * @return list<PlannedLine>
     */
    public function lines(array $items, bool $online): array
    {
        $services = Service::query()->whereKey(array_map(fn (BookingItem $i) => $i->serviceId, $items))
            ->where('is_active', true)->when($online, fn ($q) => $q->where('is_public', true))->get()->keyBy('id');
        $pets = Pet::query()->whereKey(array_map(fn (BookingItem $i) => $i->petId, $items))->get()->keyBy('id');

        $lines = [];
        foreach ($items as $i => $item) {
            $service = $services->get($item->serviceId) ?? throw ValidationException::withMessages(["items.{$i}.service_id" => 'Dịch vụ không hợp lệ.']);
            $pet = $pets->get($item->petId) ?? throw ValidationException::withMessages(["items.{$i}.pet_id" => 'Thú cưng không hợp lệ.']);
            if ($online && $item->assignedStaffId !== null) {
                throw ValidationException::withMessages(["items.{$i}.assigned_staff_id" => 'Lịch online không gán người.']);
            }
            $lines[] = new PlannedLine($pet, $service, $this->quotes->resolve($service, $pet), $item->assignedStaffId);
        }

        return $lines;
    }

    /**
     * @param  list<PlannedLine>  $lines
     * @param  list<array{0: string, 1: string}>  $keys
     */
    private function book(BookingGate $gate, BookingRequest $request, Branch $branch, array $lines, array $keys, CarbonImmutable $start): BookingResult
    {
        $online = $request->source === AppointmentSource::Online;

        $customer = $gate->lockCustomer($request->customerId);
        if ($online && $customer->is_system) {
            throw ValidationException::withMessages(['customer_id' => 'Khách không hợp lệ.']);
        }

        // Idempotency after the customer lock (ADR 0002 b).
        if ($request->idempotencyKey !== null) {
            $existing = Appointment::query()->where('idempotency_key', $request->idempotencyKey)->first();
            if ($existing !== null) {
                return new BookingResult($existing, replayed: true);
            }
        }

        $gate->ensureLinesValid($customer, $branch, $lines);
        $gate->lockCapacity((int) $customer->tenant_id, $branch->id, $keys);
        $warnings = $gate->checkCapacity($branch, $lines, $start, $online);
        $gate->ensurePetsFree($lines, $start);
        $warnings = [...$warnings, ...$gate->assignmentWarnings($lines, $branch, $start)];

        $this->takeQuota((int) $customer->tenant_id, $gate->clock());

        $appointment = Appointment::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'starts_at' => $start,
            'ends_at' => max(array_map(fn (PlannedLine $l) => $l->endsAt($start), $lines)),
            'source' => $request->source,
            'status' => AppointmentStatus::Confirmed,
            'preferred_staff_id' => $request->preferredStaffId,
            'customer_note' => $request->customerNote === null || trim($request->customerNote) === '' ? null : trim($request->customerNote),
            'send_reminders' => $request->sendReminders,
            'idempotency_key' => $request->idempotencyKey,
            'created_by_staff_id' => $request->createdByStaffId,
        ]);

        self::addLines($appointment, $lines, $online ? ActorType::Customer : ActorType::Staff, $request->createdByStaffId, $online);

        return new BookingResult($appointment, $warnings);
    }

    /**
     * Writes lines (status booked, quote snapshot, copied branch / customer / start) and one
     * `item_added` history row each. Shared with AddAppointmentItems.
     *
     * @param  list<PlannedLine>  $lines
     */
    public static function addLines(Appointment $appointment, array $lines, ActorType $actor, ?int $actorStaffId, bool $unassigned = false): void
    {
        $start = CarbonImmutable::instance($appointment->starts_at);
        foreach ($lines as $line) {
            $item = AppointmentItem::query()->create([
                'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id,
                'customer_id' => $appointment->customer_id,
                'pet_id' => $line->pet->id,
                'service_id' => $line->service->id,
                'service_group' => $line->service->service_group,
                'starts_at' => $start,
                'ends_at' => $line->endsAt($start),
                'duration_min' => $line->quote->durationMin,
                'reference_price' => $line->quote->price,
                'status' => AppointmentItemStatus::Booked,
                'assigned_staff_id' => $unassigned ? null : $line->assignedStaffId,
            ]);

            AppointmentChange::query()->create([
                'appointment_id' => $appointment->id,
                'appointment_item_id' => $item->id,
                'action' => 'item_added',
                'new_starts_at' => $start,
                'actor_type' => $actor,
                'actor_staff_id' => $actorStaffId,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * One appointment of the plan's monthly quota (shop-local month), per appointment, never given
     * back on cancel. Unlimited plans still count, for usage.
     */
    private function takeQuota(int $tenantId, ShopClock $clock): void
    {
        $period = $clock->today()->format('Y-m');
        $max = $this->context->require()->plan()->value('max_appointments_per_month');

        DB::statement('INSERT INTO tenant_usage (tenant_id, period, appointments_count) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE appointments_count = appointments_count', [$tenantId, $period]);

        $taken = DB::table('tenant_usage')->where('tenant_id', $tenantId)->where('period', $period)
            ->when($max !== null, fn ($q) => $q->where('appointments_count', '<', (int) $max))
            ->increment('appointments_count');

        if ($taken === 0) {
            throw new QuotaExceededException;
        }
    }
}
