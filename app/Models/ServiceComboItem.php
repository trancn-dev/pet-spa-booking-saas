<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Services inside a grooming combo.
 */
class ServiceComboItem extends Pivot
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'service_combo_items';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
