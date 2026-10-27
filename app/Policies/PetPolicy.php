<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** Safety alerts (allergies, behaviour) come with `pets.view`: every role that sees a pet sees them. */
class PetPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'pets.view');
    }

    public function view(User $user, Pet $pet): bool
    {
        return $this->allowed($user, 'pets.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'pets.manage');
    }

    public function update(User $user, Pet $pet): bool
    {
        return $this->allowed($user, 'pets.manage');
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->allowed($user, 'pets.delete');
    }
}
