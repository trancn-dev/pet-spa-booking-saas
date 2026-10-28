<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'plan-'.fake()->unique()->numerify('#####'),
            'name' => fake()->randomElement(['Starter', 'Pro', 'Clinic']),
            'max_branches' => 3,
            'max_staff' => 10,
            'max_appointments_per_month' => 500,
            'features' => [],
        ];
    }
}
