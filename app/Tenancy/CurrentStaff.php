<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in user's `staff` row in the current shop, and the branches it may act on
 * (staff_branches, or every branch with `branches.all`). Scoped singleton: one lookup per request.
 */
class CurrentStaff
{
    private ?Staff $staff = null;

    private ?int $resolvedFor = null;

    /** @var list<int>|null */
    private ?array $branchIds = null;

    public function __construct(private readonly TenantContext $context) {}

    public function get(): ?Staff
    {
        $user = Auth::guard('web')->user();
        $tenantId = $this->context->id();

        if (! $user instanceof User || $tenantId === null) {
            return null;
        }

        $key = $tenantId * 1_000_000_000 + (int) $user->getKey();
        if ($this->resolvedFor !== $key) {
            $this->resolvedFor = $key;
            $this->branchIds = null;
            $this->staff = Staff::query()->where('user_id', $user->getKey())->where('status', 'active')->first();
        }

        return $this->staff;
    }

    public function forget(): void
    {
        $this->staff = null;
        $this->resolvedFor = null;
        $this->branchIds = null;
    }

    public function hasAllBranches(): bool
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User && $this->get() !== null && $user->hasPermissionTo('branches.all');
    }

    /**
     * Branches this person is assigned to (staff_branches).
     *
     * @return list<int>
     */
    public function branchIds(): array
    {
        $staff = $this->get();

        if ($staff === null) {
            return [];
        }

        return $this->branchIds ??= DB::table('staff_branches')
            ->where('tenant_id', $staff->tenant_id)
            ->where('staff_id', $staff->id)
            ->pluck('branch_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($this->get() === null) {
            return false;
        }

        return $branchId === null || $this->hasAllBranches() || in_array($branchId, $this->branchIds(), true);
    }
}
