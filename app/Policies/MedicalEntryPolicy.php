<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MedicalEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;
use App\Tenancy\CurrentStaff;

/**
 * Medical records are shared by every branch (a vet at any branch reads them). Only vets write;
 * an entry is editable by its author for 24 hours, then only addenda.
 */
class MedicalEntryPolicy
{
    use ChecksPermissions;

    /** Diagnosis, treatment, internal notes. */
    public function viewFull(User $user): bool
    {
        return $this->allowed($user, 'medical.view_full');
    }

    /** Allergies, chronic conditions, exam dates only. */
    public function viewAlerts(User $user): bool
    {
        return $this->allowed($user, 'medical.view_alerts', 'medical.view_full');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'medical.write');
    }

    public function update(User $user, MedicalEntry $entry): bool
    {
        return $this->allowed($user, 'medical.write')
            && $entry->author_staff_id === app(CurrentStaff::class)->get()?->id
            && now()->lt($entry->editable_until);
    }

    public function addAddendum(User $user, MedicalEntry $entry): bool
    {
        return $this->allowed($user, 'medical.write');
    }
}
