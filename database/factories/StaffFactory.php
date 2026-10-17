<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => null,
            'display_name' => fake()->name(),
            'status' => 'active',
        ];
    }
}
