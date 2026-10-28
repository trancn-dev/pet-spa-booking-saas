<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'name' => fake()->name(),
            'phone' => '+849'.fake()->unique()->numerify('########'),
            'email' => null,
            'is_system' => false,
        ];
    }

    /**
     * The per-branch "Thú hoang / vô chủ" profile.
     */
    public function system(Branch $branch): static
    {
        return $this->state([
            'tenant_id' => $branch->tenant_id,
            'name' => 'Thú hoang / vô chủ',
            'phone' => null,
            'is_system' => true,
            'system_branch_id' => $branch->id,
        ]);
    }
}
