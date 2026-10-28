<?php

declare(strict_types=1);

namespace App\Booking;

use App\Booking\Exceptions\SlotTakenException;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentStatus;
use App\Enums\ServiceGroup;
use App\Enums\StaffStatus;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Scheduling\AvailabilityService;
use App\Scheduling\BookingLine;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one locking and capacity path of db-schema 5.1 / ADR 0002, shared by every action that puts
 * lines on the calendar: book, reschedule, add a line, restore a cancellation.
 *
 * Lock order, everywhere: customers → booking_capacity_locks ((date, group) ascending, medical
 * before grooming) → tenant_usage. The lock rows are created beforehand with prepare(), outside
 * the transaction.
 */
final class BookingGate
{
    public const CLOSED = 'Giờ này cơ sở không mở cửa (ngoài giờ, ngày nghỉ, hoặc không đúng mốc 15 phút).';

    public function __construct(
        private readonly ShopClock $clock,
        private readonly AvailabilityService $availability,
    ) {}

    public static function forCurrentShop(): self
    {
        return new self(ShopClock::current(), app(AvailabilityService::class));
    }

    public function clock(): ShopClock
    {
        return $this->clock;
    }

    /**
     * Seats per group per cell for lines starting together.
     *
     * @param  list<PlannedLine>  $lines
     * @return array{groups: array<string, array<int, int>>, span: int}
     */
    public static function plan(array $lines): array
    {
        return AvailabilityService::demand(array_map(fn (PlannedLine $l) => [$l->service->service_group, $l->quote->blockedMin() * 60], $lines));
    }

    /**
     * The (local date, group) lock rows a set of lines touches, in lock order.
     *
     * @param  list<PlannedLine>  $lines
     * @return list<array{0: string, 1: string}>
     */
    public function keys(array $lines, CarbonImmutable $start): array
    {
        $date = $this->clock->toLocal($start)->format('Y-m-d');
        $groups = array_unique(array_map(fn (PlannedLine $l) => $l->service->service_group->value, $lines));

        return self::ordered(array_map(fn (string $g) => [$date, $g], $groups));
    }

    /**
     * @param  list<array{0: string, 1: string}>  $keys
     * @return list<array{0: string, 1: string}>
     */
    public static function ordered(array $keys): array
    {
        $rank = [ServiceGroup::Medical->value => 0, ServiceGroup::Grooming->value => 1];
        $unique = [];
        foreach ($keys as [$date, $group]) {
            $unique["{$date}|{$group}"] = [$date, $group];
        }
        $keys = array_values($unique);
        usort($keys, fn (array $a, array $b) => [$a[0], $rank[$a[1]]] <=> [$b[0], $rank[$b[1]]]);

        return $keys;
    }

    /**
     * Step 0, autocommit: the lock rows must exist before any transaction locks them. Inside a
     * transaction two INSERT IGNOREs of the same new key both take an S lock and deadlock on X.
     *
     * @param  list<array{0: string, 1: string}>  $keys
     */
    public function prepare(int $tenantId, int $branchId, array $keys): void
    {
        foreach ($keys as [$date, $group]) {
            DB::insert('INSERT IGNORE INTO booking_capacity_locks (tenant_id, branch_id, service_group, `date`) VALUES (?, ?, ?, ?)', [$tenantId, $branchId, $group, $date]);
        }
    }

    /** Step 2: one customer's changes run one at a time. */
    public function lockCustomer(int $customerId): Customer
    {
        return Customer::query()->whereKey($customerId)->lockForUpdate()->first()
            ?? throw ValidationException::withMessages(['customer_id' => 'Khách không hợp lệ.']);
    }

    /**
     * Step 3: lock rows found by their whole primary key (record locks only, never gaps).
     *
     * @param  list<array{0: string, 1: string}>  $keys  in lock order
     */
    public function lockCapacity(int $tenantId, int $branchId, array $keys): void
    {
        foreach (self::ordered($keys) as [$date, $group]) {
            DB::table('booking_capacity_locks')
                ->where('tenant_id', $tenantId)->where('branch_id', $branchId)
                ->where('service_group', $group)->where('date', $date)
                ->lockForUpdate()->first();
        }
    }

    /**
     * Pets belong to the customer, services are offered at the branch.
     *
     * @param  list<PlannedLine>  $lines
     */
    public function ensureLinesValid(Customer $customer, Branch $branch, array $lines): void
    {
        foreach ($lines as $i => $line) {
            if ($line->pet->customer_id !== $customer->id) {
                throw ValidationException::withMessages(["items.{$i}.pet_id" => 'Bé '.$line->pet->name.' không phải của khách này.']);
            }
        }
        $offered = Service::query()->availableAt($branch)->whereKey(array_map(fn (PlannedLine $l) => $l->service->id, $lines))->pluck('id')->all();
        foreach ($lines as $i => $line) {
            if (! in_array($line->service->id, $offered, true)) {
                throw ValidationException::withMessages(["items.{$i}.service_id" => 'Cơ sở này không có dịch vụ '.$line->service->name.'.']);
            }
        }
    }

    /**
     * Steps 4/5, after the locks. Online: any group over its online capacity → SlotTakenException.
     * Front desk: never refused, returns warnings. Lines of `$exclude` (the appointment being
     * moved) are not counted against itself.
     *
     * @param  list<PlannedLine>  $lines
     * @return list<string> warnings
     */
    public function checkCapacity(Branch $branch, array $lines, CarbonImmutable $start, bool $online, ?int $exclude = null): array
    {
        $assessment = $this->availability->assess($branch, self::plan($lines), $start, $exclude);
        if (! $assessment['open']) {
            throw ValidationException::withMessages(['starts_at' => self::CLOSED]);
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

        return $warnings;
    }

    /**
     * Step 6: a pet is never in two overlapping appointments (lines of `$exclude` don't count).
     *
     * @param  list<PlannedLine>  $lines
     */
    public function ensurePetsFree(array $lines, CarbonImmutable $start, ?int $exclude = null): void
    {
        $until = [];
        $names = [];
        foreach ($lines as $line) {
            $end = $line->endsAt($start);
            $until[$line->pet->id] = max($until[$line->pet->id] ?? $end, $end);
            $names[$line->pet->id] = $line->pet->name;
        }

        $busy = AppointmentItem::query()
            ->whereIn('pet_id', array_keys($until))
            ->when($exclude !== null, fn ($q) => $q->where('appointment_id', '!=', $exclude))
            ->whereIn('status', self::occupying())
            ->whereHas('appointment', fn ($q) => $q->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value]))
            ->where('starts_at', '<', max($until))->where('ends_at', '>', $start)
            ->get(['pet_id', 'starts_at']);

        foreach ($busy as $other) {
            if ($other->starts_at->lt($until[$other->pet_id])) {
                throw ValidationException::withMessages(['items' => 'Bé '.$names[$other->pet_id].' đã có lịch khác chồng giờ.']);
            }
        }
    }

    /**
     * Assigned people must be active and work at the branch; one already busy is a warning.
     *
     * @param  list<PlannedLine>  $lines
     * @return list<string>
     */
    public function assignmentWarnings(array $lines, Branch $branch, CarbonImmutable $start, ?int $exclude = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (PlannedLine $l) => $l->assignedStaffId, $lines))));
        if ($ids === []) {
            return [];
        }

        $staff = Staff::query()->whereKey($ids)->where('status', StaffStatus::Active->value)
            ->where(fn ($q) => $q->where('primary_branch_id', $branch->id)->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branch->id)))
            ->get()->keyBy('id');
        if ($staff->count() !== count($ids)) {
            throw ValidationException::withMessages(['items' => 'Người được gán không làm ở cơ sở này.']);
        }

        $ends = array_map(fn (PlannedLine $l) => $l->endsAt($start), $lines);
        $busy = AppointmentItem::query()->whereIn('assigned_staff_id', $ids)
            ->when($exclude !== null, fn ($q) => $q->where('appointment_id', '!=', $exclude))
            ->whereIn('status', self::occupying())
            ->where('starts_at', '<', max($ends))->where('ends_at', '>', $start)->get(['assigned_staff_id', 'starts_at']);

        $warnings = [];
        foreach ($lines as $i => $line) {
            $id = $line->assignedStaffId;
            if ($id !== null && $busy->contains(fn (AppointmentItem $b) => $b->assigned_staff_id === $id && $b->starts_at->lt($ends[$i]))) {
                $warnings[] = $staff[$id]->display_name.' đã có lịch khác chồng giờ.';
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * The three free online starts nearest to `$start`, this day and the next six.
     *
     * @param  list<PlannedLine>  $lines
     * @return list<array{date: string, time: string, starts_at: string}>
     */
    public function suggestions(Branch $branch, array $lines, CarbonImmutable $start): array
    {
        $from = $this->clock->toLocal($start)->format('Y-m-d');
        $to = $this->clock->toLocal($start)->addDays(6)->format('Y-m-d');
        $slots = $this->availability->slotsForOnline($branch, array_map(fn (PlannedLine $l) => new BookingLine($l->service, $l->pet), $lines), $from, $to);

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

    /**
     * Online starts must leave the lead time.
     */
    public static function ensureLeadTime(CarbonImmutable $start): void
    {
        if ($start->lt(CarbonImmutable::now()->addMinutes((int) config('booking.online_lead_minutes', 60)))) {
            throw ValidationException::withMessages(['starts_at' => 'Giờ này đã qua hoặc quá sát giờ, mời chọn giờ muộn hơn.']);
        }
    }

    /**
     * @return list<string>
     */
    public static function occupying(): array
    {
        return array_map(fn (AppointmentItemStatus $s) => $s->value, AppointmentItemStatus::occupying());
    }
}
