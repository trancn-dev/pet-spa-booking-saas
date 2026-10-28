<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
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
            'kind' => InvoiceKind::Service,
            'customer_id' => $this->sameTenant(Customer::class),
            'status' => InvoiceStatus::Draft,
            'business_date' => now()->toDateString(),
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total' => '0.00',
            'discount_amount' => '0.00',
        ];
    }

    public function retail(): static
    {
        return $this->state(['kind' => InvoiceKind::Retail, 'customer_id' => null]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Completed,
            'code' => strtoupper(fake()->unique()->bothify('CS??-########-###')),
            'completed_at' => now(),
        ]);
    }
}
