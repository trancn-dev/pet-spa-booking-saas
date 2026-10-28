<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Customer;

/**
 * A branch always comes with its stray profile ("Thú hoang / vô chủ", db-schema 2.3) and a first
 * cash drawer ("Két 1"). Runs inside the current tenant; callers own the transaction and the
 * `plans.max_branches` check.
 */
class CreateBranch
{
    /**
     * @param  array{code: string, name: string, address?: string|null, phone?: string|null, opening_hours?: array<int|string, list<array{0: string, 1: string}>>|null}  $attributes
     */
    public function handle(array $attributes): Branch
    {
        $branch = Branch::query()->create([
            'code' => strtoupper($attributes['code']),
            'name' => $attributes['name'],
            'address' => $attributes['address'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'opening_hours' => $attributes['opening_hours'] ?? self::defaultHours(),
        ]);

        Customer::query()->create(['name' => 'Thú hoang / vô chủ', 'is_system' => true, 'system_branch_id' => $branch->id]);
        CashDrawer::query()->create(['branch_id' => $branch->id, 'name' => 'Két 1']);

        return $branch;
    }

    /**
     * 08:00–20:00 every day, keyed by ISO weekday (1 = Monday).
     *
     * @return array<int|string, list<array{0: string, 1: string}>>
     */
    public static function defaultHours(): array
    {
        return array_fill_keys(array_map('strval', range(1, 7)), [['08:00', '20:00']]);
    }
}
