<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class InvoicePolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allowed($user, 'invoices.view') && $this->atBranch($invoice->branch_id);
    }

    /** Add lines, take payment, complete. */
    public function charge(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft
            && $this->allowed($user, 'invoices.charge')
            && $this->atBranch($invoice->branch_id);
    }

    public function voidDraft(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft
            && $this->allowed($user, 'invoices.void_draft')
            && $this->atBranch($invoice->branch_id);
    }

    /** Sensitive: void + replacement of a completed invoice. */
    public function amend(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Completed
            && $this->allowed($user, 'invoices.amend')
            && $this->atBranch($invoice->branch_id);
    }
}
