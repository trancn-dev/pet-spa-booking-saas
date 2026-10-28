<?php

declare(strict_types=1);

namespace App\Booking;

use App\Models\AppointmentItem;
use App\Models\Pet;
use App\Models\Service;
use App\Pricing\Quote;
use Carbon\CarbonImmutable;

/** A line about to be saved (booked, moved, added or restored), with its quote. */
final readonly class PlannedLine
{
    public function __construct(
        public Pet $pet,
        public Service $service,
        public Quote $quote,
        public ?int $assignedStaffId = null,
    ) {}

    /**
     * An existing line, keeping its booked snapshot (duration, buffer, reference price): moving or
     * restoring an appointment never re-prices it. Needs `pet` and `service` loaded.
     */
    public static function fromItem(AppointmentItem $item): self
    {
        $blocked = (int) round(($item->ends_at->getTimestamp() - $item->starts_at->getTimestamp()) / 60);

        return new self($item->pet, $item->service, new Quote($item->duration_min, max(0, $blocked - $item->duration_min), (string) $item->reference_price, null, ''), $item->assigned_staff_id);
    }

    /** Every line starts with the appointment and holds its duration + buffer. */
    public function endsAt(CarbonImmutable $start): CarbonImmutable
    {
        return $start->addMinutes($this->quote->blockedMin());
    }
}
