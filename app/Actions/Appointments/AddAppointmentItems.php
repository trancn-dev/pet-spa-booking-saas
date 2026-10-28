<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Booking\AppointmentState;
use App\Booking\BookingGate;
use App\Booking\BookingItem;
use App\Booking\BookingResult;
use App\Booking\Exceptions\SlotTakenException;
use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentChanged;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds pets / services to a booked appointment through the same capacity path as a booking
 * (`item_added`). New lines start with the appointment. A pet + service line that was cancelled
 * before is booked again (UNIQUE(appointment_id, pet_id, service_id)), with a fresh quote.
 */
class AddAppointmentItems
{
    public function __construct(private readonly BookAppointment $book) {}

    /**
     * @param  list<BookingItem>  $items
     */
    public function handle(Appointment $appointment, array $items, ActorType $actor, ?int $staffId = null): BookingResult
    {
        if (! in_array($appointment->status, [AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn], true)) {
            throw ValidationException::withMessages(['items' => 'Lịch đã hủy hoặc không đến: không thêm dòng được.']);
        }
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Chọn dịch vụ cần thêm.']);
        }

        $gate = BookingGate::forCurrentShop();
        $online = $actor === ActorType::Customer;
        $lines = $this->book->lines($items, $online);
        $start = CarbonImmutable::instance($appointment->starts_at);
        $keys = $gate->keys($lines, $start);
        $gate->prepare((int) $appointment->tenant_id, $appointment->branch_id, $keys);
        $branch = Branch::query()->findOrFail($appointment->branch_id);

        try {
            $result = DB::transaction(function () use ($gate, $appointment, $branch, $lines, $keys, $start, $online, $actor, $staffId): BookingResult {
                $customer = $gate->lockCustomer($appointment->customer_id);
                $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                $gate->ensureLinesValid($customer, $branch, $lines);

                $existing = AppointmentItem::query()->where('appointment_id', $appointment->id)->lockForUpdate()->get()
                    ->keyBy(fn (AppointmentItem $i) => $i->pet_id.'|'.$i->service_id);
                foreach ($lines as $line) {
                    $same = $existing->get($line->pet->id.'|'.$line->service->id);
                    if ($same !== null && ! in_array($same->status, [AppointmentItemStatus::Cancelled, AppointmentItemStatus::NoShow], true)) {
                        throw ValidationException::withMessages(['items' => "Bé {$line->pet->name} đã có {$line->service->name} trong lịch này."]);
                    }
                }

                $gate->lockCapacity((int) $appointment->tenant_id, $appointment->branch_id, $keys);
                $warnings = $gate->checkCapacity($branch, $lines, $start, $online);
                $gate->ensurePetsFree($lines, $start, $appointment->id);
                $warnings = [...$warnings, ...$gate->assignmentWarnings($lines, $branch, $start)];

                $new = [];
                foreach ($lines as $line) {
                    $same = $existing->get($line->pet->id.'|'.$line->service->id);
                    if ($same === null) {
                        $new[] = $line;

                        continue;
                    }
                    $same->update([
                        'status' => AppointmentItemStatus::Booked, 'starts_at' => $start, 'ends_at' => $line->endsAt($start),
                        'duration_min' => $line->quote->durationMin, 'reference_price' => $line->quote->price,
                        'assigned_staff_id' => $online ? null : $line->assignedStaffId, 'visit_id' => null,
                    ]);
                    AppointmentState::log($appointment, 'item_added', $actor, $staffId, $same, null, null, $start);
                }
                BookAppointment::addLines($appointment, $new, $actor, $staffId, $online);

                $ends = AppointmentItem::query()->where('appointment_id', $appointment->id)->max('ends_at');
                $appointment->update(['ends_at' => $ends]);
                AppointmentState::sync($appointment);

                return new BookingResult($appointment, $warnings);
            }, BookAppointment::ATTEMPTS);
        } catch (SlotTakenException $e) {
            throw $e->withSuggestions($gate->suggestions($branch, $lines, $start));
        }

        AppointmentChanged::afterCommit((int) $appointment->tenant_id, $appointment->id, 'item_added');

        return $result;
    }
}
