<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceGroup;
use App\Models\Branch;
use App\Models\OnlineBookingRate;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineBookingRate>
 */
class OnlineBookingRateFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'branch_id' => $this->sameTenant(Branch::class),
            'service_group' => ServiceGroup::Medical,
            'ratio' => '0.500',
        ];
    }
}
