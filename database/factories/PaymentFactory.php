<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'kind' => PaymentKind::Payment,
            'method' => PaymentMethod::BankTransfer,
            'amount' => '100000.00',
            'received_by_staff_id' => $this->sameTenant(Staff::class),
            'paid_at' => now(),
            'idempotency_key' => fn (): string => (string) Str::uuid(),
            'created_at' => now(),
        ];
    }
}
