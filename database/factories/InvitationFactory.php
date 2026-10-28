<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'email' => fake()->unique()->safeEmail(),
            'role_names' => ['receptionist'],
            'branch_ids' => [],
            'token_hash' => hash('sha256', Str::random(32)),
            'invited_by_staff_id' => $this->sameTenant(Staff::class),
            'invited_by_admin_id' => null,
            'expires_at' => now()->addHours(72),
        ];
    }
}
