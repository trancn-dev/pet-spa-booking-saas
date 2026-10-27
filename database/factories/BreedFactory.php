<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PetSize;
use App\Models\Breed;
use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Breed>
 */
class BreedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'species_id' => Species::factory(),
            'name' => fake()->unique()->words(2, true),
            'default_size' => PetSize::M,
        ];
    }
}
