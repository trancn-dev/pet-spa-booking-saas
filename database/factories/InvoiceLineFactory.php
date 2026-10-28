<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceLineType;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'invoice_id' => $this->sameTenant(Invoice::class),
            'line_type' => InvoiceLineType::Custom,
            'name' => 'Phí dịch vụ',
            'unit_factor' => '1.000',
            'quantity' => '1.000',
            'unit_price' => '100000.00',
            'tax_rate' => '0.00',
            'line_total' => '100000.00',
        ];
    }
}
