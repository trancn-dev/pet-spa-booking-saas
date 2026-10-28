<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Models\Plan;
use App\Models\Platform\TenantSubscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantSubscription>
 */
class TenantSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory(),
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'amount' => '3000000.00',
            'paid_at' => now(),
        ];
    }
}
