<?php

declare(strict_types=1);

namespace Database\Factories\Platform;

use App\Models\Platform\ImpersonationSession;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ImpersonationSession>
 */
class ImpersonationSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform_admin_id' => PlatformAdmin::factory(),
            'tenant_id' => Tenant::factory(),
            'target_user_id' => User::factory(),
            'reason' => 'Hỗ trợ cấu hình',
            'token_hash' => hash('sha256', Str::random(32)),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
