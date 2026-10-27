<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

interface TenantMembership
{
    public function isMember(User $user, Tenant $tenant): bool;

    /**
     * True when the user has a staff row in the shop that is disabled (not merely never joined).
     */
    public function wasMember(User $user, Tenant $tenant): bool;

    /**
     * Shops where the user is active staff and the shop is not cancelled (login is allowed when
     * this is not empty). Suspended shops are included; whether they open is ResolveTenant's call.
     *
     * @return Collection<int, Tenant>
     */
    public function tenantsFor(User $user): Collection;

    /**
     * The shop to open after login: the user's last one if still open to them, else the first.
     */
    public function defaultTenantFor(User $user, ?Tenant $except = null): ?Tenant;
}
