<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Booking\BookingResult;
use App\Booking\Exceptions\QuotaExceededException;
use App\Booking\Exceptions\SlotTakenException;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\ServiceGroup;
use App\Enums\StaffStatus;
use App\Events\AppointmentBooked;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Staff;
use App\Pricing\Quote;
use App\Pricing\ServiceQuoteResolver;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingLine;
use App\Scheduling\ShopClock;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books an appointment without ever going over the online capacity (db-schema 5.1, ADR 0002).
 * Same path for the Site and the front desk.
 *
 * 0. Before the transaction (autocommit): INSERT IGNORE the capacity lock rows of every
 *    (branch, group, local day) of the booking. Never inside the transaction: two transactions
 *    inserting the same new key both hold an S lock and deadlock when upgrading to X.
 * Inside one transaction (READ COMMITTED, retried on deadlock / lock wait timeout):
 * 2. lock the customer row: one customer's bookings run one at a time (for step 6);
 * 1. idempotency key already used → return that appointment (checked after the customer lock,
 *    ADR 0002 (b), so a replay never hits the UNIQUE index);
 *    check pets, services at the branch, opening hours;
 * 3. lock the capacity rows, medical before grooming (fixed order, no deadlock);
 * 4/5. online: any group over its online capacity in any cell → SlotTakenException, nothing
 *    saved; front desk: never refused, warnings when over the people on duty;
 * 6. a pet cannot be in two overlapping appointments;
 * 7. plan quota, last and shortest lock (the shop's hottest row);
 * 8. appointment + lines + history; AppointmentBooked after commit.
 *
 * Lock order everywhere: customers → booking_capacity_locks (date, group) → tenant_usage.
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
        $clock = ShopClock::for($tenant);
        $availability = app(AvailabilityService::class); // built for the shop in context

        if ($request->items === []) {
            throw ValidationException::withMessages(['items' => 'Chọn ít nhất một dịch vụ cho một bé.']);
        }
        $online = $request->source === AppointmentSource::Online;

        // Plain reads before any lock: nothing here is trusted for capacity, only for the layout.
        $branch = Branch::query()->whereKey($request->branchId)->where('is_active', true)->first()
            ?? throw ValidationException::withMessages(['branch_id' => 'Cơ sở không hợp lệ.']);
        $services = Service::query()->whereKey(array_map(fn (BookingItem $i) => $i->serviceId, $request->items))
            ->where('is_active', true)->when($online, fn ($q) => $q->where('is_public', true))->get()->keyBy('id');
        $pets = Pet::query()->whereKey(array_map(fn (BookingItem $i) => $i->petId, $request->items))->get()->keyBy('id');

        $lines = [];
        foreach ($request->items as $i => $item) {
            $service = $services->get($item->serviceId) ?? throw ValidationException::withMessages(["items.{$i}.service_id" => 'Dịch vụ không hợp lệ.']);
            $pet = $pets->get($item->petId) ?? throw ValidationException::withMessages(["items.{$i}.pet_id" => 'Thú cưng không hợp lệ.']);
            if ($online && $item->assignedStaffId !== null) {
                throw ValidationException::withMessages(["items.{$i}.assigned_staff_id" => 'Lịch online không gán người.']);
            }
            $lines[] = ['item' => $item, 'service' => $service, 'pet' => $pet, 'quote' => $this->quotes->resolve($service, $pet)];
        }

        $start = $request->startsAt->utc();
        $earliest = CarbonImmutable::now()->addMinutes($online ? (int) config('booking.online_lead_minutes', 60) : 0);
        if ($online && $start->lt($earliest)) {
            throw ValidationException::withMessages(['starts_at' => 'Giờ này đã qua hoặc quá sát giờ, mời chọn giờ muộn hơn.']);
        }
        $plan = AvailabilityService::demand(array_map(fn (array $l) => [$l['service']->service_group, $l['quote']->blockedMin() * 60], $lines));
        // Lock order: medical before grooming.
        $groups = array_values(array_filter([ServiceGroup::Medical->value, ServiceGroup::Grooming->value], fn ($g) => isset($plan['groups'][$g])));
        $date = $clock->toLocal($start)->format('Y-m-d');

        // Step 0, autocommit.
        $this->ensureLockRows((int) $tenant->getKey(), $branch->id, $groups, $date);

        try {
            $result = DB::transaction(
                fn (): BookingResult => $this->book($request, $branch, $lines, $plan, $groups, $date, $start, $availability, $clock),
                self::ATTEMPTS,
            );
        } catch (SlotTakenException $e) {
            throw $e->withSuggestions($this->suggestions($availability, $branch, $lines, $start, $clock));
        } catch (UniqueConstraintViolationException $e) {
            // Same idempotency key from another customer's request that won the race.
            $existing = $request->idempotencyKey === null ? null : Appointment::query()->where('idempotency_key', $request->idempotencyKey)->first();
            if ($existing === null) {
                throw $e;
            }

            return new BookingResult($existing, replayed: true);
        }

        if (! $result->replayed) {
            $tenantId = (int) $tenant->getKey();
            $appointmentId = $result->appointment->id;
            DB::afterCommit(fn () => AppointmentBooked::dispatch($tenantId, $appointmentId));
        }

        return $result;
    }

    /**
     * @param  list<array{item: BookingItem, service: Service, pet: Pet, quote: Quote}>  $lines
     * @param  array{groups: array<string, array<int, int>>, span: int}  $plan
     * @param  list<string>  $groups
     */
    private function book(BookingRequest $request, Branch $branch, array $lines, array $plan, array $groups, string $date, CarbonImmutable $start, AvailabilityService $availability, ShopClock $clock): BookingResult
    {
        $online = $request->source === AppointmentSource::Online;

        // (2) Customer row first.
        $customer = Customer::query()->whereKey($request->customerId)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['customer_id' => 'Khách không hợp lệ.']);
        if ($online && $customer->is_system) {
            throw ValidationException::withMessages(['customer_id' => 'Khách không hợp lệ.']);
        }

        // (1) Idempotency, after the customer lock (ADR 0002 b).
        if ($request->idempotencyKey !== null) {
            $existing = Appointment::query()->where('idempotency_key', $request->idempotencyKey)->first();
            if ($existing !== null) {
                return new BookingResult($existing, replayed: true);
            }
        }

        foreach ($lines as $i => $line) {
            if ($line['pet']->customer_id !== $customer->id) {
                throw ValidationException::withMessages(["items.{$i}.pet_id" => 'Bé '.$line['pet']->name.' không phải của khách này.']);
            }
        }
        $offered = Service::query()->availableAt($branch)->whereKey(array_map(fn (array $l) => $l['service']->id, $lines))->pluck('id')->all();
        foreach ($lines as $i => $line) {
            if (! in_array($line['service']->id, $offered, true)) {
                throw ValidationException::withMessages(["items.{$i}.service_id" => 'Cơ sở này không có dịch vụ '.$line['service']->name.'.']);
            }
        }

        // (3) Capacity rows, medical before grooming.
        DB::table('booking_capacity_locks')
            ->where('tenant_id', $customer->tenant_id)->where('branch_id', $branch->id)->where('date', $date)
            ->whereIn('service_group', $groups)
            ->orderByRaw("FIELD(service_group, 'medical', 'grooming')")
            ->lockForUpdate()->get();

        // (4)(5) Capacity, read after the locks (READ COMMITTED: sees every booking committed before us).
        $assessment = $availability->assess($branch, $plan, $start);
        if (! $assessment['open']) {
            throw ValidationException::withMessages(['starts_at' => 'Giờ này cơ sở không mở cửa (ngoài giờ, ngày nghỉ, hoặc không đúng mốc 15 phút).']);
        }

        $warnings = [];
        foreach ($assessment['groups'] as $g => $check) {
            $label = ServiceGroup::from($g)->label();
            if ($online && ! $check['fits_online']) {
                throw new SlotTakenException;
            }
            if (! $online && $check['no_duty']) {
                $warnings[] = "Không có ai trực nhóm {$label} trong khung này.";
            } elseif (! $online && $check['over_duty']) {
                $warnings[] = "Vượt số người trực nhóm {$label}: vẫn lưu, cần sắp xếp người.";
            }
        }

        $ends = array_map(fn (array $l) => $start->addMinutes($l['quote']->blockedMin()), $lines);

        // (6) A pet is never in two overlapping appointments.
        $this->ensurePetsFree($lines, $start, $ends);

        $warnings = [...$warnings, ...$this->assignmentWarnings($lines, $branch, $start, $ends)];

        // (7) Plan quota, last lock.
        $this->takeQuota((int) $customer->tenant_id, $clock);

        // (8) Write.
        $appointment = Appointment::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'starts_at' => $start,
            'ends_at' => max($ends),
            'source' => $request->source,
            'status' => AppointmentStatus::Confirmed,
            'preferred_staff_id' => $request->preferredStaffId,
            'customer_note' => $request->customerNote === null || trim($request->customerNote) === '' ? null : trim($request->customerNote),
            'send_reminders' => $request->sendReminders,
            'idempotency_key' => $request->idempotencyKey,
            'created_by_staff_id' => $request->createdByStaffId,
        ]);

        foreach ($lines as $i => $line) {
            $item = AppointmentItem::query()->create([
                'appointment_id' => $appointment->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'pet_id' => $line['pet']->id,
                'service_id' => $line['service']->id,
                'service_group' => $line['service']->service_group,
                'starts_at' => $start,
                'ends_at' => $ends[$i],
                'duration_min' => $line['quote']->durationMin,
                'reference_price' => $line['quote']->price,
                'status' => AppointmentItemStatus::Booked,
                'assigned_staff_id' => $online ? null : $line['item']->assignedStaffId,
            ]);

            AppointmentChange::query()->create([
                'appointment_id' => $appointment->id,
                'appointment_item_id' => $item->id,
                'action' => 'item_added',
                'new_starts_at' => $start,
                'actor_type' => $online ? ActorType::Customer : ActorType::Staff,
                'actor_staff_id' => $request->createdByStaffId,
                'created_at' => now(),
            ]);
        }

        return new BookingResult($appointment, $warnings);
    }

    /**
     * @param  list<string>  $groups
     */
    private function ensureLockRows(int $tenantId, int $branchId, array $groups, string $date): void
    {
        foreach ($groups as $group) {
            DB::insert('INSERT IGNORE INTO booking_capacity_locks (tenant_id, branch_id, service_group, `date`) VALUES (?, ?, ?, ?)', [$tenantId, $branchId, $group, $date]);
        }
    }

    /**
     * @param  list<array{item: BookingItem, service: Service, pet: Pet, quote: Quote}>  $lines
     * @param  list<CarbonImmutable>  $ends
     */
    private function ensurePetsFree(array $lines, CarbonImmutable $start, array $ends): void
    {
        $until = [];
        foreach ($lines as $i => $line) {
            $until[$line['pet']->id] = max($until[$line['pet']->id] ?? $ends[$i], $ends[$i]);
        }

        $busy = AppointmentItem::query()
            ->whereIn('pet_id', array_keys($until))
            ->whereIn('status', array_map(fn (AppointmentItemStatus $s) => $s->value, AppointmentItemStatus::occupying()))
            ->whereHas('appointment', fn ($q) => $q->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value]))
            ->where('starts_at', '<', max($until))->where('ends_at', '>', $start)
            ->get(['pet_id', 'starts_at', 'ends_at']);

        foreach ($busy as $other) {
            if ($other->starts_at->lt($until[$other->pet_id])) {
                $pet = collect($lines)->first(fn (array $l) => $l['pet']->id === $other->pet_id)['pet'] ?? null;
                throw ValidationException::withMessages(['items' => 'Bé '.($pet->name ?? '').' đã có lịch khác chồng giờ.']);
            }
        }
    }

    /**
     * Assigned people must be active and work at the branch; one already busy is a warning.
     *
     * @param  list<array{item: BookingItem, service: Service, pet: Pet, quote: Quote}>  $lines
     * @param  list<CarbonImmutable>  $ends
     * @return list<string>
     */
    private function assignmentWarnings(array $lines, Branch $branch, CarbonImmutable $start, array $ends): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (array $l) => $l['item']->assignedStaffId, $lines))));
        if ($ids === []) {
            return [];
        }

        $staff = Staff::query()->whereKey($ids)->where('status', StaffStatus::Active->value)
            ->where(fn ($q) => $q->where('primary_branch_id', $branch->id)->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branch->id)))
            ->get()->keyBy('id');
        if ($staff->count() !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'Người được gán không làm ở cơ sở này.']);
        }

        /** @var Collection<int, AppointmentItem> $busy */
        $busy = AppointmentItem::query()->whereIn('assigned_staff_id', $ids)
            ->whereIn('status', array_map(fn (AppointmentItemStatus $s) => $s->value, AppointmentItemStatus::occupying()))
            ->where('starts_at', '<', max($ends))->where('ends_at', '>', $start)->get(['assigned_staff_id', 'starts_at']);

        $warnings = [];
        foreach ($lines as $i => $line) {
            $id = $line['item']->assignedStaffId;
            if ($id !== null && $busy->contains(fn (AppointmentItem $b) => $b->assigned_staff_id === $id && $b->starts_at->lt($ends[$i]))) {
                $warnings[] = $staff[$id]->display_name.' đã có lịch khác chồng giờ.';
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * One appointment of the plan's monthly quota (shop-local month of booking), counted per
     * appointment, never returned on cancel. Unlimited plans still count, for usage.
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

    /**
     * The three free starts nearest to the one asked for, this day and the next six.
     *
     * @param  list<array{item: BookingItem, service: Service, pet: Pet, quote: Quote}>  $lines
     * @return list<array{date: string, time: string, starts_at: string}>
     */
    private function suggestions(AvailabilityService $availability, Branch $branch, array $lines, CarbonImmutable $start, ShopClock $clock): array
    {
        $from = $clock->toLocal($start)->format('Y-m-d');
        $to = $clock->toLocal($start)->addDays(6)->format('Y-m-d');
        $slots = $availability->slotsForOnline($branch, array_map(fn (array $l) => new BookingLine($l['service'], $l['pet']), $lines), $from, $to);

        $all = [];
        foreach ($slots as $date => $starts) {
            foreach ($starts as $s) {
                $all[] = ['date' => $date, ...$s];
            }
        }
        usort($all, fn (array $a, array $b) => abs(CarbonImmutable::parse($a['starts_at'])->getTimestamp() - $start->getTimestamp())
            <=> abs(CarbonImmutable::parse($b['starts_at'])->getTimestamp() - $start->getTimestamp()));

        return array_slice($all, 0, 3);
    }
}
