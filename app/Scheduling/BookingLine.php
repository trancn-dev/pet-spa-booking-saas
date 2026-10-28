<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Pet;
use App\Models\Service;
use App\Pricing\PetProfile;

/**
 * One line of a booking being planned: a pet and a service. Lines of the same pet run one after
 * the other in the given order (an exam, then the spa); different pets run at the same time
 * (two dogs examined together need two seats).
 */
final readonly class BookingLine
{
    public function __construct(
        public Service $service,
        public Pet|PetProfile $pet,
        /** Groups lines by pet: a pet id, or any key for pets not saved yet. */
        public int|string $petKey,
    ) {}
}
