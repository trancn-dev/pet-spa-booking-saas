<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceGroup;
use App\Tenancy\BelongsToTenant;
use Database\Factories\StaffWorkingHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Weekly roster slot. Times are shop-local; weekday is ISO (1 = Monday).
 *
 * @property ServiceGroup|null $service_group
 * @property string $start_time
 * @property string $end_time
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
class StaffWorkingHour extends Model
{
    /** @use HasFactory<StaffWorkingHourFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'staff_working_hours';

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'service_group' => ServiceGroup::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }
}
