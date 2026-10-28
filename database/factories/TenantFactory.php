<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(2),
            'plan_id' => Plan::factory(),
            'status' => TenantStatus::Active,
            'timezone' => 'Asia/Ho_Chi_Minh',
        ];
    }

    public function trial(): static
    {
        return $this->state(['status' => TenantStatus::Trial, 'plan_expires_at' => now()->addDays(14)]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => TenantStatus::Suspended, 'suspended_at' => now()]);
    }
}
