<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\Plan;
use App\Models\Tenant;
use Database\Factories\Platform\TenantSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per sale / renewal; tenants.plan_id + plan_expires_at summarise it.
 */
class TenantSubscription extends Model
{
    /** @use HasFactory<TenantSubscriptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
