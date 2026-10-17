<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the `{tenant}` (slug) route parameter, `/app/{tenant}/...`.
 * Unknown slug, or a logged-in user who is not a member → 404 (never reveal existence).
 * Guests pass through so `auth` can redirect them to login.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantMembership $membership,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $tenant = $slug instanceof Tenant
            ? $slug
            : (is_string($slug) ? Tenant::query()->where('slug', $slug)->first() : null);

        abort_if($tenant === null || $tenant->status !== 'active', 404);

        $user = $request->user();
        if ($user !== null) {
            abort_unless($this->membership->isMember($user, $tenant), 404);

            if ($user->last_tenant_id !== $tenant->getKey()) {
                $user->forceFill(['last_tenant_id' => $tenant->getKey()])->saveQuietly();
            }
        }

        $request->route()?->setParameter('tenant', $tenant);
        URL::defaults(['tenant' => $tenant->slug]);
        $this->context->set($tenant);

        try {
            return $next($request);
        } finally {
            $this->context->forget();
        }
    }
}
