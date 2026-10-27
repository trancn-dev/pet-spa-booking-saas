<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceGroup;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffWorkingHour>
 */
class StaffWorkingHourFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'staff_id' => $this->sameTenant(Staff::class),
            'branch_id' => $this->sameTenant(Branch::class),
            'weekday' => fake()->numberBetween(1, 7),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'service_group' => ServiceGroup::Medical,
        ];
    }
}
