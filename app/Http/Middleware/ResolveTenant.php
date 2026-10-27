<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentStaff;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the `{tenant}` (slug) route parameter, `/app/{tenant}/...`.
 * Unknown / closed shop (see TenantStatus::isAccessible), or a logged-in user who is not a member
 * → 404 (never reveal existence).
 * Guests pass through so `auth` can redirect them to login.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantMembership $membership,
        private readonly PermissionRegistrar $permissions,
        private readonly CurrentStaff $currentStaff,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant');
        $tenant = $slug instanceof Tenant
            ? $slug
            : (is_string($slug) ? Tenant::query()->where('slug', $slug)->first() : null);

        abort_if($tenant === null || ! $tenant->status->isAccessible(), 404);

        $user = $request->user();
        if ($user !== null) {
            abort_unless($this->membership->isMember($user, $tenant), 404);

            if ($user->last_tenant_id !== $tenant->getKey()) {
                $user->forceFill(['last_tenant_id' => $tenant->getKey()])->saveQuietly();
            }
        }

        // The tenant lives in TenantContext from here on. Drop the parameter so controllers only get
        // their own (`{role}`, `{pet}`, …): route parameters are passed by position.
        $request->route()?->forgetParameter('tenant');
        URL::defaults(['tenant' => $tenant->slug]);
        $this->context->set($tenant);

        // Roles and permissions are per shop: answer for this one, never from a cached relation
        // loaded for another shop.
        $this->permissions->setPermissionsTeamId($tenant->getKey());
        $user?->unsetRelation('roles')->unsetRelation('permissions');
        $this->currentStaff->forget();

        try {
            return $next($request);
        } finally {
            $this->context->forget();
            $this->permissions->setPermissionsTeamId(null);
            $this->currentStaff->forget();
        }
    }
}
