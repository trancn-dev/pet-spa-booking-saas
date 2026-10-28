<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffTimeOff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTimeOff>
 */
class StaffTimeOffFactory extends Factory
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
            'starts_at' => now()->startOfHour(),
            'ends_at' => now()->startOfHour()->addHours(4),
            'reason' => 'Nghỉ phép',
        ];
    }
}
