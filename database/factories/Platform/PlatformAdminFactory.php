<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => PlatformAdminRole::SuperAdmin,
            'is_active' => true,
        ];
    }
}
