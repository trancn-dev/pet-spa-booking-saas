<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\ReminderStatus;
use App\Models\Reminder;

/** Pending reminders of an appointment that no longer match it (moved, cancelled). */
final class Reminders
{
    public static function skipPending(int $appointmentId): void
    {
        Reminder::query()->where('remindable_type', 'appointment')->where('remindable_id', $appointmentId)
            ->where('status', ReminderStatus::Pending->value)
            ->update(['status' => ReminderStatus::Skipped->value]);
    }
}
