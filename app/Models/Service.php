<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceGroup;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Price is shared by every branch and applied when added to a Visit.
 *
 * @property ServiceGroup $service_group
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsToMany<Service, $this, ServiceComboItem>
     */
    public function comboItems(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_combo_items', 'combo_service_id', 'item_service_id')->using(ServiceComboItem::class)->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<ServicePriceRule, $this>
     */
    public function priceRules(): HasMany
    {
        return $this->hasMany(ServicePriceRule::class);
    }

    /**
     * @return BelongsToMany<Branch, $this, BranchService>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_services', 'service_id', 'branch_id')->using(BranchService::class);
    }

    /**
     * Offered at the branch: no `branch_services` row at all means every branch (db-schema 2.4).
     *
     * @param  Builder<Service>  $query
     */
    #[Scope]
    protected function availableAt(Builder $query, Branch|int $branch): void
    {
        $id = $branch instanceof Branch ? $branch->getKey() : $branch;

        $query->where(fn (Builder $q) => $q->whereDoesntHave('branches')
            ->orWhereHas('branches', fn (Builder $b) => $b->where('branch_services.branch_id', $id)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_group' => ServiceGroup::class,
            'is_combo' => 'boolean',
            'duration_min' => 'integer',
            'buffer_min' => 'integer',
            'price' => 'decimal:2',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
