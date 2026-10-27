<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** The catalogue and prices are shared by every branch. */
class ServicePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'services.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'services.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->allowed($user, 'services.manage');
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->allowed($user, 'services.manage');
    }
}
