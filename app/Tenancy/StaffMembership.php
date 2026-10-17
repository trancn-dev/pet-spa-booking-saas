<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;

/**
 * Membership = an active, non-deleted `staff` row linking the user to the tenant.
 * Looked up across tenants on purpose (the tenant context is not set yet).
 */
class StaffMembership implements TenantMembership
{
    public function __construct(private readonly TenantContext $context) {}

    public function isMember(User $user, Tenant $tenant): bool
    {
        return $this->context->withoutTenancy(
            fn () => Staff::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->exists()
        );
    }

    public function defaultTenantFor(User $user): ?Tenant
    {
        $last = $user->last_tenant_id;

        if ($last !== null) {
            $tenant = Tenant::query()->find($last);
            if ($tenant !== null && $this->isMember($user, $tenant)) {
                return $tenant;
            }
        }

        $first = $this->context->withoutTenancy(
            fn () => Staff::query()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->orderBy('tenant_id')
                ->value('tenant_id')
        );

        return $first === null ? null : Tenant::query()->find($first);
    }
}
