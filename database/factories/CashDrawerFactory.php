<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CashDrawer;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashDrawer>
 */
class CashDrawerFactory extends Factory
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
            'name' => 'Két '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
