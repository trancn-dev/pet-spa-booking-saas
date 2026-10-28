<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\AppointmentState;
use App\Booking\Reminders;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels a whole appointment or some of its lines ("Hủy cả lịch hoặc từng pet", S3). Cancelled
 * lines free their seats; the other lines stay. A reason is required from staff; customers on the
 * Site may cancel only up to booking_cancel_cutoff_hours before the start. "Hoàn tác" within a few
 * seconds goes through RestoreCancellation. `item_removed` is the same move for a line taken off a
 * booking (S2 edit) rather than a pet that will not come.
 */
class CancelAppointment
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<int>|null  $itemIds  null = every line still booked
     */
    public function handle(Appointment $appointment, ?array $itemIds, ?string $reason, ActorType $actor, ?int $staffId = null, bool $notifyCustomer = false, string $action = 'cancelled'): Appointment
    {
        $reason = $reason === null ? null : trim($reason);
        if ($actor === ActorType::Staff && ($reason === null || $reason === '')) {
            throw ValidationException::withMessages(['reason' => 'Chọn hoặc nhập lý do hủy.']);
        }

        $result = DB::transaction(function () use ($appointment, $itemIds, $reason, $actor, $staffId, $action): Appointment {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if ($actor === ActorType::Customer) {
                $cutoff = (int) ($this->context->require()->settings()->value('booking_cancel_cutoff_hours') ?? 24);
                if (CarbonImmutable::now()->addHours($cutoff)->gt($appointment->starts_at)) {
                    throw ValidationException::withMessages(['reason' => "Chỉ hủy online được trước giờ hẹn {$cutoff} giờ. Vui lòng gọi shop."]);
                }
            }

            $lines = AppointmentItem::query()->where('appointment_id', $appointment->id)
                ->when($itemIds !== null, fn ($q) => $q->whereKey($itemIds), fn ($q) => $q->where('status', AppointmentItemStatus::Booked->value))
                ->lockForUpdate()->get();
            if ($lines->isEmpty() || ($itemIds !== null && $lines->count() !== count(array_unique($itemIds)))) {
                throw ValidationException::withMessages(['items' => 'Không có dòng nào để hủy.']);
            }

            foreach ($lines as $line) {
                AppointmentState::move($line, AppointmentItemStatus::Cancelled);
                AppointmentState::log($appointment, $action, $actor, $staffId, $line, $reason);
            }

            if (AppointmentState::sync($appointment) === AppointmentStatus::Cancelled) {
                $appointment->update(['cancelled_at' => now(), 'cancelled_by_type' => $actor, 'cancel_reason' => $reason]);
                AppointmentState::log($appointment, 'cancelled', $actor, $staffId, null, $reason);
                Reminders::skipPending($appointment->id);
            }

            return $appointment;
        });

        AppointmentChanged::afterCommit((int) $result->tenant_id, $result->id, $action, $notifyCustomer);

        return $result;
    }
}
