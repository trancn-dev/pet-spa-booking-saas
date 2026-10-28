<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class PetVaccinationPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'vaccinations.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'vaccinations.manage');
    }
}
