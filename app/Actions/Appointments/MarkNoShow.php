<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\AppointmentState;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Không đến": booked lines → no_show, freeing their seats; no penalty. The front desk may mark it
 * once the time has started; the end-of-day command marks what is left. Lines already handled are
 * left alone, so running it twice changes nothing.
 */
class MarkNoShow
{
    /**
     * @param  list<int>|null  $itemIds  null = every line still booked
     * @return int lines marked
     */
    public function handle(Appointment $appointment, ?array $itemIds, ActorType $actor, ?int $staffId = null): int
    {
        $marked = DB::transaction(function () use ($appointment, $itemIds, $actor, $staffId): int {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($actor === ActorType::Staff && $appointment->starts_at->isFuture()) {
                throw ValidationException::withMessages(['items' => 'Chưa tới giờ hẹn: chưa đánh dấu không đến được.']);
            }

            $lines = AppointmentItem::query()->where('appointment_id', $appointment->id)
                ->when($itemIds !== null, fn ($q) => $q->whereKey($itemIds))
                ->where('status', AppointmentItemStatus::Booked->value)
                ->lockForUpdate()->get();

            foreach ($lines as $line) {
                AppointmentState::move($line, AppointmentItemStatus::NoShow);
                AppointmentState::log($appointment, 'no_show', $actor, $staffId, $line);
            }
            if ($lines->isNotEmpty()) {
                AppointmentState::sync($appointment);
            }

            return $lines->count();
        });

        if ($marked > 0) {
            AppointmentChanged::afterCommit((int) $appointment->tenant_id, $appointment->id, 'no_show');
        }

        return $marked;
    }

    /**
     * Every line of the shop still booked that started before today (shop time).
     */
    public function endOfDay(CarbonImmutable $todayStartUtc): int
    {
        $ids = AppointmentItem::query()->where('status', AppointmentItemStatus::Booked->value)
            ->where('starts_at', '<', $todayStartUtc)->distinct()->pluck('appointment_id');

        $marked = 0;
        foreach (Appointment::query()->whereKey($ids)->get() as $appointment) {
            $marked += $this->handle($appointment, null, ActorType::System);
        }

        return $marked;
    }
}
