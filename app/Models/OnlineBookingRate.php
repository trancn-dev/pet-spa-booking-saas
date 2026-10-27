<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceGroup;
use App\Tenancy\BelongsToTenant;
use Database\Factories\OnlineBookingRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Share of on-duty staff bookable online, per branch × group (optionally per weekday / time).
 */
class OnlineBookingRate extends Model
{
    /** @use HasFactory<OnlineBookingRateFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

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
            'service_group' => ServiceGroup::class,
            'ratio' => 'decimal:3',
            'weekday' => 'integer',
        ];
    }
}
