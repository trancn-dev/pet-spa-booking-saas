<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CareProtocol;
use App\Models\Species;
use App\Models\Vaccine;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareProtocol>
 */
class CareProtocolFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'name' => 'Phác đồ 7 bệnh',
            'species_id' => fn (): int => Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó'])->id,
            'vaccine_id' => $this->sameTenant(Vaccine::class),
            'is_active' => true,
        ];
    }
}
