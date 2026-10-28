<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Platform\PlatformAdmin;
use Illuminate\Http\Request;

/**
 * Shared props of the /admin area: the platform admin instead of the CMS user.
 */
class HandleAdminInertiaRequests extends HandleInertiaRequests
{
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $shared = parent::share($request);
        unset($shared['auth'], $shared['quote']);

        return [
            ...$shared,
            'admin' => function () use ($request): ?array {
                $admin = $request->user('platform');

                return $admin instanceof PlatformAdmin ? [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role->value,
                    'is_super_admin' => $admin->isSuperAdmin(),
                ] : null;
            },
            'environment' => config('platform.environment_label') ?? (app()->isProduction() ? 'Production' : ucfirst((string) app()->environment())),
            'flash' => ['status' => fn () => $request->session()->get('status')],
        ];
    }
}
