<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Branches a staff member works at (primary + extra ones).
 */
class StaffBranch extends Pivot
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'staff_branches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
