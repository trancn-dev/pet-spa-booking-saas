<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Tenancy\CurrentStaff;

/** Everyone sees their own roster; `rosters.view` sees everyone's, `rosters.manage` edits (own branches). */
class StaffWorkingHourPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'rosters.view');
    }

    public function view(User $user, StaffWorkingHour $slot): bool
    {
        return $slot->staff_id === app(CurrentStaff::class)->get()?->id || $this->allowed($user, 'rosters.view');
    }

    public function manage(User $user, Branch|int|null $branch = null): bool
    {
        return $this->allowed($user, 'rosters.manage') && $this->atBranch($branch);
    }
}
