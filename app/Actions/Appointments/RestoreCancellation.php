<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\AppointmentState;
use App\Booking\BookingGate;
use App\Booking\BookingResult;
use App\Booking\PlannedLine;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Hoàn tác" a cancellation within `booking.undo_seconds` (S3 toast): the lines of the last
 * cancellation go back to booked, but only through the capacity check again (their seats may have
 * been taken meanwhile). Logged as `restored`.
 */
class RestoreCancellation
{
    public function handle(Appointment $appointment, ActorType $actor, ?int $staffId = null): BookingResult
    {
        $gate = BookingGate::forCurrentShop();
        $online = $actor === ActorType::Customer;
        $start = CarbonImmutable::instance($appointment->starts_at);

        $items = $this->lastCancelled($appointment);
        $lines = array_map(fn (AppointmentItem $i) => PlannedLine::fromItem($i), $items);
        $keys = $gate->keys($lines, $start);
        $gate->prepare((int) $appointment->tenant_id, $appointment->branch_id, $keys);
        $branch = Branch::query()->findOrFail($appointment->branch_id);

        $result = DB::transaction(function () use ($gate, $appointment, $branch, $keys, $start, $online, $actor, $staffId): BookingResult {
            $gate->lockCustomer($appointment->customer_id);
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            $items = $this->lastCancelled($appointment); // again, under the lock
            $lines = array_map(fn (AppointmentItem $i) => PlannedLine::fromItem($i), $items);

            $gate->lockCapacity((int) $appointment->tenant_id, $appointment->branch_id, $keys);
            $warnings = $gate->checkCapacity($branch, $lines, $start, $online);
            $gate->ensurePetsFree($lines, $start, $appointment->id);

            foreach ($items as $item) {
                AppointmentState::move($item, AppointmentItemStatus::Booked);
                AppointmentState::log($appointment, 'restored', $actor, $staffId, $item);
            }
            AppointmentState::sync($appointment);

            return new BookingResult($appointment->refresh(), $warnings);
        }, BookAppointment::ATTEMPTS);

        AppointmentChanged::afterCommit((int) $appointment->tenant_id, $appointment->id, 'restored');

        return $result;
    }

    /**
     * Lines of the latest cancellation, if it is recent enough to undo.
     *
     * @return list<AppointmentItem>
     */
    private function lastCancelled(Appointment $appointment): array
    {
        $last = AppointmentChange::query()->where('appointment_id', $appointment->id)
            ->whereIn('action', ['cancelled', 'item_removed'])->whereNotNull('appointment_item_id')
            ->latest('created_at')->latest('id')->first();
        $window = (int) config('booking.undo_seconds', 5);
        if ($last === null || $last->created_at->lt(now()->subSeconds($window))) {
            throw ValidationException::withMessages(['items' => 'Đã quá thời gian hoàn tác.']);
        }

        $ids = AppointmentChange::query()->where('appointment_id', $appointment->id)
            ->where('action', $last->action)->where('created_at', $last->created_at)->pluck('appointment_item_id');
        $items = AppointmentItem::query()->with(['pet', 'service'])->whereKey($ids)
            ->where('status', AppointmentItemStatus::Cancelled->value)->orderBy('id')->get()->all();

        return $items === [] ? throw ValidationException::withMessages(['items' => 'Không còn gì để hoàn tác.']) : $items;
    }
}
