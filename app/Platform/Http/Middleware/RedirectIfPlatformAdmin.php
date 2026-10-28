<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login / 2FA / invitation pages: a signed-in admin goes straight to the shop list.
 */
class RedirectIfPlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('platform')->check()) {
            return to_route('admin.tenants.index');
        }

        return $next($request);
    }
}
