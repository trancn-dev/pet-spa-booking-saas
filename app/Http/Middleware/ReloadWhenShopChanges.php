<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser keeps Ziggy's route defaults (the shop slug) from the first full page load. An Inertia visit that
 * enters a shop from outside it (login) or moves between two shops would build links for the wrong shop, so the
 * visit becomes a full page load instead. Runs after ResolveTenant.
 */
class ReloadWhenShopChanges
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if ($tenant === null || ! $request->header('X-Inertia') || ! $request->isMethod('GET')) {
            return $next($request);
        }

        $referer = (string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);
        $from = preg_match('#^/app/([^/]+)#', $referer, $m) === 1 ? $m[1] : null;

        if ($from !== $tenant->slug) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
