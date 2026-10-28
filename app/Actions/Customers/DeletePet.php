<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\AppointmentItemStatus;
use App\Models\AppointmentItem;
use App\Models\Pet;
use App\Support\ActivityLog;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a pet; refused while it is still booked for an upcoming appointment.
 */
class DeletePet
{
    public const HAS_UPCOMING = 'Bé còn lịch hẹn sắp tới. Hủy lịch trước khi xóa hồ sơ.';

    public function handle(Pet $pet): void
    {
        $booked = AppointmentItem::query()->where('pet_id', $pet->getKey())
            ->where('status', AppointmentItemStatus::Booked->value)
            ->where('ends_at', '>', now())
            ->exists();

        if ($booked) {
            throw ValidationException::withMessages(['pet' => self::HAS_UPCOMING]);
        }

        $pet->delete();
        ActivityLog::record('pet.deleted', $pet, ['name' => $pet->name]);
    }
}
