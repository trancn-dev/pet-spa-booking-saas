<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\AppointmentState;
use App\Booking\BookingGate;
use App\Booking\BookingResult;
use App\Booking\Exceptions\SlotTakenException;
use App\Booking\PlannedLine;
use App\Booking\Reminders;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Dời lịch": every booked line moves to the new start, through the same locks and capacity check
 * as a booking (BookingGate), never re-priced. The rows of the old and the new (date, group) are
 * all locked, in (date, group) order, so two moves across the same days never deadlock. The
 * appointment's own lines are not counted against itself. Customers (Site) follow online rules,
 * the lead time and booking_reschedule_cutoff_hours; staff are only warned. Pending reminders are
 * skipped; reminders for the new time come from AppointmentChanged (task 6.1).
 */
class RescheduleAppointment
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Appointment $appointment, CarbonImmutable $newStart, ActorType $actor, ?int $staffId = null, ?string $reason = null): BookingResult
    {
        $gate = BookingGate::forCurrentShop();
        $online = $actor === ActorType::Customer;
        $newStart = $newStart->utc();

        $lines = $this->lines($appointment);
        $oldStart = CarbonImmutable::instance($appointment->starts_at);
        if ($online) {
            BookingGate::ensureLeadTime($newStart);
        }

        $keys = BookingGate::ordered([...$gate->keys($lines, $oldStart), ...$gate->keys($lines, $newStart)]);
        $gate->prepare((int) $appointment->tenant_id, $appointment->branch_id, $keys);
        $branch = Branch::query()->findOrFail($appointment->branch_id);

        try {
            $result = DB::transaction(function () use ($gate, $appointment, $branch, $keys, $newStart, $online, $actor, $staffId, $reason): BookingResult {
                $gate->lockCustomer($appointment->customer_id);
                $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                $items = $this->bookedItems($appointment);
                $lines = array_map(fn (AppointmentItem $i) => PlannedLine::fromItem($i), $items);
                $old = CarbonImmutable::instance($appointment->starts_at);

                if ($online) {
                    $cutoff = (int) ($this->context->require()->settings()->value('booking_reschedule_cutoff_hours') ?? 24);
                    if (CarbonImmutable::now()->addHours($cutoff)->gt($old)) {
                        throw ValidationException::withMessages(['starts_at' => "Chỉ dời online được trước giờ hẹn {$cutoff} giờ. Vui lòng gọi shop."]);
                    }
                }

                $gate->lockCapacity((int) $appointment->tenant_id, $appointment->branch_id, $keys);
                $warnings = $gate->checkCapacity($branch, $lines, $newStart, $online, $appointment->id);
                $gate->ensurePetsFree($lines, $newStart, $appointment->id);
                $warnings = [...$warnings, ...$gate->assignmentWarnings($lines, $branch, $newStart, $appointment->id)];

                foreach ($items as $i => $item) {
                    $item->update(['starts_at' => $newStart, 'ends_at' => $lines[$i]->endsAt($newStart)]);
                }
                $appointment->update([
                    'starts_at' => $newStart,
                    'ends_at' => max(array_map(fn (PlannedLine $l) => $l->endsAt($newStart), $lines)),
                ]);
                AppointmentState::log($appointment, 'rescheduled', $actor, $staffId, null, $reason, $old, $newStart);
                Reminders::skipPending($appointment->id);

                return new BookingResult($appointment, $warnings);
            }, BookAppointment::ATTEMPTS);
        } catch (SlotTakenException $e) {
            throw $e->withSuggestions($gate->suggestions($branch, $lines, $newStart));
        }

        AppointmentChanged::afterCommit((int) $appointment->tenant_id, $appointment->id, 'rescheduled');

        return $result;
    }

    /**
     * @return list<PlannedLine>
     */
    private function lines(Appointment $appointment): array
    {
        if ($appointment->status !== AppointmentStatus::Confirmed) {
            throw ValidationException::withMessages(['starts_at' => 'Chỉ dời được lịch chưa check-in, chưa hủy.']);
        }

        return array_map(fn (AppointmentItem $i) => PlannedLine::fromItem($i), $this->bookedItems($appointment));
    }

    /**
     * @return list<AppointmentItem>
     */
    private function bookedItems(Appointment $appointment): array
    {
        if ($appointment->status !== AppointmentStatus::Confirmed) {
            throw ValidationException::withMessages(['starts_at' => 'Chỉ dời được lịch chưa check-in, chưa hủy.']);
        }
        $items = AppointmentItem::query()->with(['pet', 'service'])->where('appointment_id', $appointment->id)
            ->where('status', AppointmentItemStatus::Booked->value)->orderBy('id')->get()->all();

        return $items === [] ? throw ValidationException::withMessages(['starts_at' => 'Lịch không còn dòng nào để dời.']) : $items;
    }
}
