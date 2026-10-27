<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Species;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'customer_id' => $this->sameTenant(Customer::class),
            'species_id' => fn (): int => Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó'])->id,
            'breed_id' => null,
            'name' => fake()->firstName(),
            'sex' => fake()->randomElement(PetSex::cases()),
            'size' => PetSize::M,
            'weight_kg' => fake()->randomFloat(2, 2, 30),
            'is_neutered' => false,
            'is_stray' => false,
        ];
    }

    /**
     * A pet of the given customer.
     */
    public function of(Customer $customer): static
    {
        return $this->state(['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id]);
    }
}
