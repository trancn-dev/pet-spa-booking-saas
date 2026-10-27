<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Services offered at a branch. No row for a service = offered everywhere.
 */
class BranchService extends Pivot
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'branch_services';
}
