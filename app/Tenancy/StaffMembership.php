<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Enums\StaffStatus;
use App\Enums\TenantStatus;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Membership = an active, non-deleted `staff` row linking the user to the tenant.
 * Looked up across tenants on purpose (the tenant context is not set yet).
 */
class StaffMembership implements TenantMembership
{
    public function __construct(private readonly TenantContext $context) {}

    public function isMember(User $user, Tenant $tenant): bool
    {
        return $this->staffStatus($user, $tenant) === StaffStatus::Active;
    }

    public function wasMember(User $user, Tenant $tenant): bool
    {
        return $this->staffStatus($user, $tenant) === StaffStatus::Disabled;
    }

    public function tenantsFor(User $user): Collection
    {
        $ids = $this->context->withoutTenancy(
            fn () => Staff::query()
                ->where('user_id', $user->getKey())
                ->where('status', StaffStatus::Active)
                ->pluck('tenant_id')
        );

        return Tenant::query()
            ->whereIn('id', $ids)
            ->where('status', '!=', TenantStatus::Cancelled)
            ->orderBy('name')
            ->get();
    }

    public function defaultTenantFor(User $user, ?Tenant $except = null): ?Tenant
    {
        $open = $this->tenantsFor($user)
            ->filter(fn (Tenant $tenant): bool => $tenant->status->isAccessible() && ! $tenant->is($except));

        return $open->firstWhere('id', $user->last_tenant_id) ?? $open->sortBy('id')->first();
    }

    private function staffStatus(User $user, Tenant $tenant): ?StaffStatus
    {
        $status = $this->context->withoutTenancy(
            fn () => Staff::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('user_id', $user->getKey())
                ->value('status')
        );

        return $status === null ? null : ($status instanceof StaffStatus ? $status : StaffStatus::from((string) $status));
    }
}
