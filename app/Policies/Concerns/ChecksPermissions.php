<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Authorization\CurrentBranch;
use App\Models\Branch;
use App\Models\User;

/**
 * Permission + branch-scope checks shared by policies. Permissions come from the user's roles
 * in the current shop only (team id set by ResolveTenant). No Gate::before for owners (ADR 0009).
 */
trait ChecksPermissions
{
    protected function allowed(User $user, string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->checkPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    protected function atBranch(Branch|int|null $branch): bool
    {
        return CurrentBranch::allows($branch);
    }
}
