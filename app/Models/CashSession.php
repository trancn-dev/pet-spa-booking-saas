<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashSessionStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Till session; at most one open per drawer (open_key).
 *
 * @property CashSessionStatus $status
 */
class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'open_key'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<CashDrawer, $this>
     */
    public function drawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class, 'drawer_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'opened_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'closed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CashSessionStatus::class,
            'opened_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closed_at' => 'datetime',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }
}
