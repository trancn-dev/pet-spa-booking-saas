<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S12.2 "Thêm cơ sở": within `plans.max_branches` (NULL = unlimited). The tenant row is locked so
 * two owners adding a branch at once cannot both pass the check.
 */
class AddBranch
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateBranch $create,
    ) {}

    /**
     * @param  array{code: string, name: string, address?: string|null, phone?: string|null, opening_hours?: array<int|string, list<array{0: string, 1: string}>>|null}  $attributes
     */
    public function handle(array $attributes): Branch
    {
        return DB::transaction(function () use ($attributes): Branch {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($this->context->require()->getKey());
            $max = Plan::query()->whereKey($tenant->plan_id)->value('max_branches');

            if ($max !== null && Branch::query()->count() >= (int) $max) {
                throw ValidationException::withMessages([
                    'code' => "Gói hiện tại cho tối đa {$max} cơ sở. Liên hệ PawBook để nâng gói.",
                ]);
            }

            $branch = $this->create->handle($attributes);
            ActivityLog::record('branch.created', $branch, ['code' => $branch->code, 'name' => $branch->name], branchId: $branch->id);

            return $branch;
        });
    }
}
