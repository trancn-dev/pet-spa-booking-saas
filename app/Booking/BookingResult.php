<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\Appointment;

/**
 * The booked appointment, the warnings the front desk should see (it may overbook knowingly),
 * and whether it is an earlier booking returned for the same idempotency key.
 */
final readonly class BookingResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Appointment $appointment,
        public array $warnings = [],
        public bool $replayed = false,
    ) {}
}
