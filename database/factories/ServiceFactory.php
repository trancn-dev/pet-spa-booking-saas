<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ServiceGroup;
use App\Models\Service;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'code' => 'DV'.fake()->unique()->numerify('#####'),
            'name' => fake()->randomElement(['Khám tổng quát', 'Tiêm phòng', 'Xét nghiệm máu']),
            'service_group' => ServiceGroup::Medical,
            'is_combo' => false,
            'duration_min' => 30,
            'buffer_min' => 0,
            'price' => '200000.00',
            'is_public' => true,
            'is_active' => true,
        ];
    }

    public function grooming(): static
    {
        return $this->state([
            'name' => fake()->randomElement(['Tắm gội', 'Cắt tỉa lông']),
            'service_group' => ServiceGroup::Grooming,
            'duration_min' => 60,
            'buffer_min' => 15,
            'price' => '250000.00',
        ]);
    }
}
