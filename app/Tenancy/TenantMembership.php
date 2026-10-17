<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use App\Models\User;

interface TenantMembership
{
    public function isMember(User $user, Tenant $tenant): bool;

    /**
     * The tenant to open after login: the user's last one if still a member, else their first.
     */
    public function defaultTenantFor(User $user): ?Tenant;
}
