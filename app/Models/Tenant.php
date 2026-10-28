<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Platform\TenantSubscription;
use App\Tenancy\TenantContext;
use Closure;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A shop. The tenant itself is intentionally NOT tenant-scoped.
 * Routes bind it by slug (`/app/{tenant:slug}`); public_id is for links outside the CMS.
 *
 * @property TenantStatus $status
 * @property Carbon|null $plan_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $suspended_at
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $guarded = ['id', 'public_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'trial',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'locale' => 'vi',
        'currency' => 'VND',
    ];

    /**
     * Run a callback once per tenant, each inside its own tenant context (scheduler, backfills).
     *
     * @param  Closure(Tenant): mixed  $callback
     */
    public static function forEach(Closure $callback): void
    {
        $context = app(TenantContext::class);

        static::query()->orderBy('id')->each(
            fn (Tenant $tenant) => $context->runAs($tenant, fn () => $callback($tenant))
        );
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Requires this tenant to be the current one (TenantSetting is tenant-scoped).
     *
     * @return HasOne<TenantSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(TenantSetting::class);
    }

    /**
     * Requires this tenant to be the current one (Branch is tenant-scoped).
     *
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<TenantSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'plan_expires_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
