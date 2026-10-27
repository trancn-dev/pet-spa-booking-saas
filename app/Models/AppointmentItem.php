<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentItemStatus;
use App\Enums\ServiceGroup;
use App\Tenancy\BelongsToTenant;
use Database\Factories\AppointmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pet + one service (or grooming combo). Occupies one capacity slot of its group while booked / checked in.
 */
class AppointmentItem extends Model
{
    /** @use HasFactory<AppointmentItemFactory> */
    use BelongsToTenant, HasFactory;

    /** Statuses that hold a capacity slot. */
    public const OCCUPYING_STATUSES = [AppointmentItemStatus::Booked, AppointmentItemStatus::CheckedIn];

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'booked',
    ];

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_group' => ServiceGroup::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_min' => 'integer',
            'reference_price' => 'decimal:2',
            'status' => AppointmentItemStatus::class,
        ];
    }
}
