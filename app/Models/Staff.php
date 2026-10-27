<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StaffStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person working at one shop; for an account holder it is also the user ↔ tenant membership
 * (an active row = member). `user_id` NULL = no account (guard, cleaner).
 * Permissions come from roles (task 4.2), never from `title`.
 * CMS notifications (task 6.1) are sent to Staff, not User, as StaffNotification rows.
 */
class Staff extends Model
{
    /** @use HasFactory<StaffFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'staff';

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['pin_hash'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    /**
     * @return BelongsToMany<Branch, $this, StaffBranch>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'staff_branches', 'staff_id', 'branch_id')
            ->using(StaffBranch::class)
            ->withPivot('is_primary');
    }

    /**
     * @return HasMany<StaffWorkingHour, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    /**
     * @return HasMany<StaffTimeOff, $this>
     */
    public function timeOff(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StaffStatus::class,
        ];
    }
}
