<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PlatformAdminInvitation>
 */
class PlatformAdminInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => PlatformAdminRole::Support,
            'token_hash' => hash('sha256', Str::random(32)),
            'invited_by_admin_id' => PlatformAdmin::factory(),
            'expires_at' => now()->addHours(72),
        ];
    }
}
