<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashDrawer;
use App\Models\CashSession;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSession>
 */
class CashSessionFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'drawer_id' => $this->sameTenant(CashDrawer::class),
            'branch_id' => fn (array $a): int => CashDrawer::query()->findOrFail($a['drawer_id'])->branch_id,
            'status' => CashSessionStatus::Open,
            'opened_by' => $this->sameTenant(Staff::class),
            'opened_at' => now(),
            'opening_amount' => '0.00',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CashSessionStatus::Closed,
            'closed_by' => $attributes['opened_by'],
            'closed_at' => now(),
            'expected_cash' => '0.00',
            'counted_cash' => '0.00',
            'difference' => '0.00',
        ]);
    }
}
