<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VaccinationStatus;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Vaccine;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetVaccination>
 */
class PetVaccinationFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'pet_id' => $this->sameTenant(Pet::class),
            'vaccine_id' => $this->sameTenant(Vaccine::class),
            'administered_on' => now()->toDateString(),
            'batch_no' => fake()->bothify('LOT-####'),
            'next_due_on' => now()->addDays(21)->toDateString(),
            'status' => VaccinationStatus::Given,
        ];
    }
}
