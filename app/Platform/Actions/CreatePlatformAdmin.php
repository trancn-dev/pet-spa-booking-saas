<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Support\Facades\Validator;

/**
 * The only two ways an admin comes to exist: `platform:admin-create` (first admin) and
 * accepting an invitation. There is no admin sign-up page.
 */
class CreatePlatformAdmin
{
    public function handle(string $name, string $email, string $password, PlatformAdminRole $role): PlatformAdmin
    {
        Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:191', 'unique:platform_admins,email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        )->validate();

        return PlatformAdmin::query()->create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
