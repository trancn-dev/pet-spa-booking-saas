<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ServiceGroup;
use App\Models\Branch;
use App\Models\User;
use App\Models\Visit;
use App\Policies\Concerns\ChecksPermissions;

/**
 * Taking / finishing a medical Visit needs `visits.medical` (vets only: an owner who is not also
 * a vet cannot); a grooming Visit needs `visits.grooming` (groomers, owner).
 */
class VisitPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'visits.view');
    }

    public function view(User $user, Visit $visit): bool
    {
        return $this->allowed($user, 'visits.view') && $this->atBranch($visit->branch_id);
    }

    /** Intake, walk-in, emergency. */
    public function create(User $user, Branch|int|null $branch = null): bool
    {
        return $this->allowed($user, 'visits.intake') && $this->atBranch($branch);
    }

    public function take(User $user, Visit $visit): bool
    {
        $permission = $visit->service_group === ServiceGroup::Medical ? 'visits.medical' : 'visits.grooming';

        return $this->allowed($user, $permission) && $this->atBranch($visit->branch_id);
    }

    public function complete(User $user, Visit $visit): bool
    {
        return $this->take($user, $visit);
    }

    public function cancel(User $user, Visit $visit): bool
    {
        return $this->allowed($user, 'visits.intake') && $this->atBranch($visit->branch_id);
    }

    public function prescribe(User $user, Visit $visit): bool
    {
        return $visit->service_group === ServiceGroup::Medical
            && $this->allowed($user, 'prescriptions.manage')
            && $this->atBranch($visit->branch_id);
    }
}
