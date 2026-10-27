<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before StartSession on /admin: the admin area reads and writes its own session cookie,
 * so the CMS session (users) and the admin session (platform_admins) never share an id.
 */
class UsePlatformSessionCookie
{
    public function __construct(private readonly SessionManager $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->sessions->driver();
        $previous = $store->getName();
        $store->setName((string) config('platform.session_cookie'));

        try {
            return $next($request);
        } finally {
            $store->setName($previous);
        }
    }
}
