<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * Fired after commit when an appointment moves, loses or gains lines, is cancelled, restored or
 * marked no-show (task 5.6). Reminders (task 6.1) listen: pending ones are already skipped on
 * reschedule / cancel; new ones are created from here. Ids only, never models.
 */
final class AppointmentChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $appointmentId,
        /** rescheduled | cancelled | restored | no_show | item_added | item_removed */
        public readonly string $action,
        /** Staff ticked "Báo khách" (cancel): tell the customer. */
        public readonly bool $notifyCustomer = false,
    ) {}

    public static function afterCommit(int $tenantId, int $appointmentId, string $action, bool $notify = false): void
    {
        DB::afterCommit(fn () => self::dispatch($tenantId, $appointmentId, $action, $notify));
    }
}
