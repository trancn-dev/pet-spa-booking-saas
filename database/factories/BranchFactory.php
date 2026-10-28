<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'code' => strtoupper(fake()->unique()->bothify('CS??##')),
            'name' => 'Cơ sở '.fake()->city(),
            'address' => fake()->streetAddress(),
            'phone' => '+8428'.fake()->numerify('########'),
            'opening_hours' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], [['08:00', '20:00']]),
            'is_active' => true,
        ];
    }
}
