<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StaffStatus;
use App\Models\Branch;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'user_id' => null,
            'primary_branch_id' => $this->sameTenant(Branch::class),
            'display_name' => fake()->name(),
            'color' => fake()->hexColor(),
            'status' => StaffStatus::Active,
        ];
    }

    /**
     * Mirror the primary branch into staff_branches, as inviting / creating staff does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Staff $staff): void {
            $staff->branches()->attach($staff->primary_branch_id, [
                'tenant_id' => $staff->tenant_id,
                'is_primary' => true,
            ]);
        });
    }
}
