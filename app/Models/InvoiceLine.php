<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceLineType;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `visit_item` lines are read-only at the till; `product` and `custom` lines are editable while draft.
 */
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['unit_cost'];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<VisitItem, $this>
     */
    public function visitItem(): BelongsTo
    {
        return $this->belongsTo(VisitItem::class);
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_type' => InvoiceLineType::class,
            'unit_factor' => 'decimal:3',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }
}
