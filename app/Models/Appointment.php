<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToTenant;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One customer, one or more pets (one item per pet + service). Reference only: no deposit.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory, HasPublicId;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'public_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'confirmed',
        'send_reminders' => true,
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
     * @return HasMany<AppointmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class);
    }

    /**
     * @return HasMany<AppointmentChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(AppointmentChange::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'preferred_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'source' => AppointmentSource::class,
            'status' => AppointmentStatus::class,
            'send_reminders' => 'boolean',
            'cancelled_at' => 'datetime',
            'cancelled_by_type' => ActorType::class,
            'no_show_at' => 'datetime',
        ];
    }
}
