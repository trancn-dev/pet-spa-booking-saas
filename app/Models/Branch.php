<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A location of the shop. Not an isolation boundary: no global scope (ADR 0004).
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToTenant, HasFactory, HasPublicId, SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'public_id'];

    /**
     * @return BelongsToMany<Staff, $this, StaffBranch>
     */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_branches', 'branch_id', 'staff_id')->using(StaffBranch::class)->withPivot('is_primary');
    }

    /**
     * @return BelongsToMany<Service, $this, BranchService>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'branch_services', 'branch_id', 'service_id')->using(BranchService::class);
    }

    /**
     * @return HasMany<CashDrawer, $this>
     */
    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    /**
     * @return HasMany<StaffWorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    /**
     * @return HasMany<BranchClosure, $this>
     */
    public function closures(): HasMany
    {
        return $this->hasMany(BranchClosure::class);
    }

    /**
     * @return HasMany<OnlineBookingRate, $this>
     */
    public function onlineBookingRates(): HasMany
    {
        return $this->hasMany(OnlineBookingRate::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'allowed_ips' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
