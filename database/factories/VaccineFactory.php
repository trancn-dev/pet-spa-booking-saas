<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VaccineKind;
use App\Models\Species;
use App\Models\Vaccine;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vaccine>
 */
class VaccineFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'name' => 'Vaccine 7 bệnh',
            'kind' => VaccineKind::Vaccine,
            'species_id' => fn (): int => Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó'])->id,
            'manufacturer' => 'Zoetis',
        ];
    }
}
