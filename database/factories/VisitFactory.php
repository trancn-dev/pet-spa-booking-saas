<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceGroup;
use App\Enums\VisitStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Visit;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
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
            'customer_id' => $this->sameTenant(Customer::class),
            'pet_id' => fn (array $a) => Pet::factory()->state(['tenant_id' => $a['tenant_id'], 'customer_id' => $a['customer_id']]),
            'invoice_id' => null,
            'service_group' => ServiceGroup::Medical,
            'visit_date' => now()->toDateString(),
            'queue_no' => fake()->unique()->numberBetween(1, 65000),
            'status' => VisitStatus::Waiting,
            'is_emergency' => false,
            'priority_at' => now(),
            'checked_in_at' => now(),
        ];
    }

    /**
     * Emergency intake before the owner / pet profile exists.
     */
    public function emergency(): static
    {
        return $this->state([
            'customer_id' => null,
            'pet_id' => null,
            'is_emergency' => true,
            'emergency_label' => 'Chó cấp cứu',
        ]);
    }
}
