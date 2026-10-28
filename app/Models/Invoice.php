<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Immutable once completed: amending voids it and issues a replacement (ADR 0005).
 *
 * @property InvoiceKind $kind
 * @property InvoiceStatus $status
 * @property Carbon $business_date
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTenant, HasFactory, HasPublicId;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'public_id', 'open_draft_key'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'replaces_invoice_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'completed_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'voided_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'business_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
