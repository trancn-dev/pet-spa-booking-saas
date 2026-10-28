<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after the booking transaction commits (reminders hook in at task 6.1). Carries ids, never
 * models: listeners load them inside the shop's context.
 */
final class AppointmentBooked
{
    use Dispatchable;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $appointmentId,
    ) {}
}
