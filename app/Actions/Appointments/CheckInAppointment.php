<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Actions\Visits\CreateVisit;
use App\Booking\AppointmentState;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentStatus;
use App\Enums\StaffStatus;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Staff;
use App\Models\Visit;
use App\Scheduling\ShopClock;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Check-in" (S3, F3): the lines of the pets that came, on the appointment's day. Lines of the same
 * pet and group share one Visit (exam + vaccine = one exam Visit; exam + spa = two Visits), each
 * with its queue number, in the same transaction. A line already linked to a Visit is skipped,
 * so checking in twice changes nothing. Lines of a pet that did not come stay booked.
 *
 * Queue priority (db-schema 2.6): arriving within ±walk_in_priority_minutes of the booked time
 * keeps the booked time; later or much earlier counts as a walk-in from the check-in time.
 */
class CheckInAppointment
{
    public function __construct(
        private readonly CreateVisit $visits,
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  list<int>  $itemIds  lines of the pets that came
     * @param  array<int, int>  $assign  line id => staff id, for lines not assigned yet
     * @return list<Visit> the Visits opened or reused
     */
    public function handle(Appointment $appointment, array $itemIds, array $assign = [], ?Staff $by = null): array
    {
        if ($itemIds === []) {
            throw ValidationException::withMessages(['items' => 'Chọn bé đã đến.']);
        }

        return DB::transaction(function () use ($appointment, $itemIds, $assign, $by): array {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
                throw ValidationException::withMessages(['items' => 'Lịch đã hủy hoặc đánh dấu không đến.']);
            }

            $clock = ShopClock::current();
            $now = CarbonImmutable::now();
            if ($clock->toLocal($appointment->starts_at)->format('Y-m-d') !== $clock->today($now)->format('Y-m-d')) {
                throw ValidationException::withMessages(['items' => 'Chỉ check-in trong ngày hẹn. Lịch ngày khác: dời lịch trước.']);
            }

            $lines = AppointmentItem::query()->where('appointment_id', $appointment->id)->orderBy('id')->lockForUpdate()->get();
            $chosen = $lines->whereIn('id', $itemIds);
            if ($chosen->count() !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages(['items' => 'Có dòng không thuộc lịch này.']);
            }

            $staff = $this->staff(array_values(array_unique($assign)), $appointment->branch_id);

            $window = (int) ($this->context->require()->settings()->value('walk_in_priority_minutes') ?? 15);
            $onTime = abs($now->getTimestamp() - $appointment->starts_at->getTimestamp()) <= $window * 60;
            $priorityAt = $onTime ? CarbonImmutable::instance($appointment->starts_at) : $now;

            // Visits already opened for this appointment, by pet + group (an earlier check-in).
            $visitFor = [];
            foreach ($lines->whereNotNull('visit_id') as $line) {
                $visitFor[$line->pet_id.'|'.$line->service_group->value] ??= $line->visit_id;
            }

            $touched = [];
            foreach ($chosen as $line) {
                if ($line->visit_id !== null) {
                    $touched[$line->visit_id] = true; // already in: idempotent

                    continue;
                }
                if ($line->status !== AppointmentItemStatus::Booked) {
                    AppointmentState::move($line, AppointmentItemStatus::CheckedIn); // throws with the reason
                }

                $assigned = $line->assigned_staff_id ?? (isset($assign[$line->id]) ? $staff[$assign[$line->id]]->id : null);
                $key = $line->pet_id.'|'.$line->service_group->value;
                $visitFor[$key] ??= $this->visits->handle(
                    branchId: $appointment->branch_id,
                    customerId: $appointment->customer_id,
                    petId: $line->pet_id,
                    group: $line->service_group,
                    priorityAt: $priorityAt,
                    assignedStaffId: $assigned,
                    reason: $appointment->customer_note,
                    createdBy: $by?->id,
                )->id;

                AppointmentState::move($line, AppointmentItemStatus::CheckedIn);
                $line->update(['visit_id' => $visitFor[$key], 'assigned_staff_id' => $assigned]);
                AppointmentState::log($appointment, 'checked_in', ActorType::Staff, $by?->id, $line);
                $touched[$visitFor[$key]] = true;
            }

            AppointmentState::sync($appointment);

            return Visit::query()->whereKey(array_keys($touched))->orderBy('queue_no')->get()->all();
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, Staff>
     */
    private function staff(array $ids, int $branchId): array
    {
        if ($ids === []) {
            return [];
        }
        $staff = Staff::query()->whereKey($ids)->where('status', StaffStatus::Active->value)
            ->where(fn ($q) => $q->where('primary_branch_id', $branchId)->orWhereHas('branches', fn ($b) => $b->where('branches.id', $branchId)))
            ->get()->keyBy('id')->all();
        if (count($staff) !== count($ids)) {
            throw ValidationException::withMessages(['assign' => 'Người được phân không làm ở cơ sở này.']);
        }

        return $staff;
    }
}
