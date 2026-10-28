<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Enums\PetSize;
use App\Models\Pet;

/**
 * What a quote depends on: species, breed, size. A saved Pet, or a pet the customer is adding on
 * the Site while booking (task 7.x) that has no row yet.
 */
final readonly class PetProfile
{
    public function __construct(
        public ?int $speciesId,
        public ?int $breedId,
        public ?PetSize $size,
    ) {}

    public static function fromPet(Pet $pet): self
    {
        return new self($pet->species_id, $pet->breed_id, $pet->size);
    }
}
