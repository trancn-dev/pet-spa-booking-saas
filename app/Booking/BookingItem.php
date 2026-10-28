<?php

declare(strict_types=1);

namespace App\Booking;

/**
 * One line of a booking: a pet of the customer and a service, optionally assigned to someone
 * (front desk only; online lines are never assigned).
 */
final readonly class BookingItem
{
    public function __construct(
        public int $petId,
        public int $serviceId,
        public ?int $assignedStaffId = null,
    ) {}
}
