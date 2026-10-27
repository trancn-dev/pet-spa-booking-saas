<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class BranchPolicy
{
    use ChecksPermissions;

    /** Work at this branch: assigned to it, or `branches.all`. */
    public function access(User $user, Branch $branch): bool
    {
        return $this->atBranch($branch);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->allowed($user, 'branches.manage') && $this->atBranch($branch);
    }
}
