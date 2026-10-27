<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Models\Platform\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protected /admin pages. Anyone who is not a signed-in, active platform admin gets a 404:
 * the admin area never reveals itself (only /admin/login is reachable as a guest).
 */
class AuthenticatePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('platform');
        $admin = $guard->user();

        if (! $admin instanceof PlatformAdmin) {
            abort(404);
        }

        if (! $admin->is_active) {
            $guard->logout();
            $request->session()->invalidate();
            abort(404);
        }

        Auth::shouldUse('platform');

        return $next($request);
    }
}
