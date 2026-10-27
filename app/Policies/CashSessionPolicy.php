<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class CashSessionPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'cash_sessions.view');
    }

    public function open(User $user, Branch|int|null $branch = null): bool
    {
        return $this->allowed($user, 'cash_sessions.manage') && $this->atBranch($branch);
    }

    public function close(User $user, CashSession $session): bool
    {
        return $this->allowed($user, 'cash_sessions.manage') && $this->atBranch($session->branch_id);
    }
}
