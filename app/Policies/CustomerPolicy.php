<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

/** Customers are shared by every branch of the shop: no branch scope. */
class CustomerPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->allowed($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->allowed($user, 'customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return ! $customer->is_system && $this->allowed($user, 'customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return ! $customer->is_system && $this->allowed($user, 'customers.delete');
    }
}
