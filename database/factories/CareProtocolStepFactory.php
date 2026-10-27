<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CareProtocol;
use App\Models\CareProtocolStep;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareProtocolStep>
 */
class CareProtocolStepFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'protocol_id' => $this->sameTenant(CareProtocol::class),
            'step_no' => fake()->unique()->numberBetween(1, 250),
            'min_age_days' => 42,
            'offset_days' => 21,
            'is_recurring' => false,
        ];
    }
}
