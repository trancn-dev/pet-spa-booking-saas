<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class AppointmentPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->allowed($user, 'appointments.view') && $this->atBranch($appointment->branch_id);
    }

    public function create(User $user, Branch|int|null $branch = null): bool
    {
        return $this->allowed($user, 'appointments.manage') && $this->atBranch($branch);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->allowed($user, 'appointments.manage') && $this->atBranch($appointment->branch_id);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }
}
