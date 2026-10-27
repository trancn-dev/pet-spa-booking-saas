<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VisitItemType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\VisitItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service, medication or consumable used in a Visit. Billable rows sync to the draft invoice.
 *
 * @property VisitItemType $item_type
 */
class VisitItem extends Model
{
    /** @use HasFactory<VisitItemFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'performed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_type' => VisitItemType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'is_billable' => 'boolean',
            'suggested_quantity' => 'decimal:3',
            'unit_factor' => 'decimal:3',
        ];
    }
}
