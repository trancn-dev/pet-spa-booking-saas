<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S12.2: name, address, phone, opening hours, active flag. The code never changes: it prefixes
 * every document number of the branch.
 */
class UpdateBranch
{
    /**
     * @param  array{name: string, address?: string|null, phone?: string|null, opening_hours: array<int|string, list<array{0: string, 1: string}>>, is_active: bool}  $attributes
     */
    public function handle(Branch $branch, array $attributes): Branch
    {
        return DB::transaction(function () use ($branch, $attributes): Branch {
            if (! $attributes['is_active'] && $branch->is_active && Branch::query()->where('is_active', true)->count() === 1) {
                throw ValidationException::withMessages(['is_active' => 'Shop cần ít nhất một cơ sở đang hoạt động.']);
            }

            $branch->fill([
                'name' => $attributes['name'],
                'address' => $attributes['address'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'opening_hours' => $attributes['opening_hours'],
                'is_active' => $attributes['is_active'],
            ]);

            $changes = array_intersect_key($branch->getDirty(), array_flip(['name', 'address', 'phone', 'opening_hours', 'is_active']));
            $branch->save();

            if ($changes !== []) {
                ActivityLog::record('branch.updated', $branch, $changes, branchId: $branch->id);
            }

            return $branch;
        });
    }
}
