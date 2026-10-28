<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class StaffPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'staff.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'staff.manage');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->allowed($user, 'staff.manage');
    }
}
