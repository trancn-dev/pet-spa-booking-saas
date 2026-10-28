<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentStaff;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMembership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the `{tenant}` (slug) route parameter, `/app/{tenant}/...`.
 * Unknown / closed shop (see TenantStatus::isAccessible), or a logged-in user who never was a
 * member → 404 (never reveal existence). A member disabled during their session is moved to
 * another shop, or signed out when none is left (task 4.3).
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

        $user = $request->user('web');
        if ($user !== null && ! $this->membership->isMember($user, $tenant)) {
            // Disabled here while signed in: move to another shop, or sign out when none is left.
            abort_unless($this->membership->wasMember($user, $tenant), 404);

            return $this->leave($request, $user);
        }

        if ($user !== null) {
            if ($user->last_tenant_id !== $tenant->getKey()) {
                $user->forceFill(['last_tenant_id' => $tenant->getKey()])->saveQuietly();
            }
        }

        // The tenant lives in TenantContext from here on. Drop the parameter so controllers only get
        // their own (`{role}`, `{pet}`, …): route parameters are passed by position.
        $request->route()?->forgetParameter('tenant');
        // Both keys: the path placeholder is `{tenant}`, but routes bound as `{tenant:slug}` look the
        // default up as `tenant:slug` (otherwise every URL gets a stray `?tenant=`).
        URL::defaults(['tenant' => $tenant->slug, 'tenant:slug' => $tenant->slug]);
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

    private function leave(Request $request, User $user): Response
    {
        $other = $this->membership->defaultTenantFor($user);

        if ($other !== null) {
            return redirect()->route('app.dashboard', ['tenant' => $other->slug])
                ->with('status', 'Tài khoản của bạn không còn hoạt động ở shop đó.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => LoginRequest::NO_ACTIVE_SHOP]);
    }
}
