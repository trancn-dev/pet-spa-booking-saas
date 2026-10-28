<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Models\Pet;
use App\Models\Service;
use App\Pricing\PetProfile;

/**
 * One line of a booking being planned: a pet (saved, or a profile on the Site) and a service.
 * Every line of a booking starts at the booking's start (db-schema 5.1): two dogs examined together
 * need two seats, an exam + spa needs a seat in each group.
 */
final readonly class BookingLine
{
    public function __construct(
        public Service $service,
        public Pet|PetProfile $pet,
    ) {}
}
