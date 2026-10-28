<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PetSize;
use App\Models\Service;
use App\Models\ServicePriceRule;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePriceRule>
 */
class ServicePriceRuleFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'service_id' => $this->sameTenant(Service::class),
            'species_id' => null,
            'breed_id' => null,
            'size' => PetSize::L,
            'duration_min' => 45,
            'price' => '300000.00',
        ];
    }
}
