<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MedicalRecord;
use App\Models\Pet;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRecord>
 */
class MedicalRecordFactory extends Factory
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
            'record_no' => 'HS-'.fake()->unique()->numerify('######'),
            'opened_at' => now(),
        ];
    }
}
