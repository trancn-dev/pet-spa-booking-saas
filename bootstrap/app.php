<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use App\Platform\Http\Middleware\AuthenticatePlatformAdmin;
use App\Platform\Http\Middleware\HandleAdminInertiaRequests;
use App\Platform\Http\Middleware\RequirePlatformRole;
use App\Platform\Http\Middleware\UsePlatformSessionCookie;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Platform admin area (ADR 0008): same app, its own middleware group and session cookie.
            Route::middleware('platform')->prefix('admin')->name('admin.')->group(base_path('routes/admin.php'));
        },
    )
    ->withCommands([__DIR__.'/../app/Platform/Console'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'platform.auth' => AuthenticatePlatformAdmin::class,
            'platform.role' => RequirePlatformRole::class,
        ]);

        // Resolve the tenant (and check membership) before auth/route-binding run.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: ResolveTenant::class);
        // The admin session cookie name must be set before the session starts.
        $middleware->prependToPriorityList(before: StartSession::class, prepend: UsePlatformSessionCookie::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Like `web`, but with the admin session cookie and admin Inertia props. No tenant middleware.
        $middleware->group('platform', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            UsePlatformSessionCookie::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
            SubstituteBindings::class,
            HandleAdminInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
