<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** `roles.manage` exists only on the owner role and can never be granted (no self-promotion). */
class RolePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'roles.manage');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return ! $role->isOwner() && $this->allowed($user, 'roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return ! $role->isOwner() && $this->allowed($user, 'roles.manage');
    }
}
