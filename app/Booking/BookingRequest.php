<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\AppointmentSource;
use Carbon\CarbonImmutable;

/**
 * Input of BookAppointment (task 5.5), the same for the Site and the front desk. `startsAt` is UTC
 * and on the 15-minute grid; every line starts then. `idempotencyKey` is a UUID from the client:
 * sending the same request twice returns the first appointment.
 */
final readonly class BookingRequest
{
    /**
     * @param  list<BookingItem>  $items
     */
    public function __construct(
        public int $branchId,
        public int $customerId,
        public array $items,
        public CarbonImmutable $startsAt,
        public AppointmentSource $source,
        public ?string $idempotencyKey = null,
        public ?int $preferredStaffId = null,
        public ?string $customerNote = null,
        public ?int $createdByStaffId = null,
        public bool $sendReminders = true,
    ) {}
}
