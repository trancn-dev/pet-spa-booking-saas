<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Models\Concerns\AppendOnly;
use App\Tenancy\BelongsToTenant;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money in / out (append-only, never changes invoice_id; ADR 0010).
 *
 * @property PaymentKind $kind
 * @property PaymentMethod $method
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use AppendOnly, BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function transferOf(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'transfer_payment_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'received_by_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
