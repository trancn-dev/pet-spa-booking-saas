<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Models\Platform\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `platform.role:super_admin` — role check inside the admin area (after AuthenticatePlatformAdmin).
 */
class RequirePlatformRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('platform');

        abort_unless($admin instanceof PlatformAdmin && in_array($admin->role->value, $roles, true), 403);

        return $next($request);
    }
}
