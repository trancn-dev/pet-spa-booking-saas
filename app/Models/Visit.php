<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceGroup;
use App\Enums\VisitStatus;
use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToTenant;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One exam / grooming of one pet: queue ticket + work done. Independent of the invoice.
 *
 * @property ServiceGroup $service_group
 * @property VisitStatus $status
 * @property Carbon $visit_date
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use BelongsToTenant, HasFactory, HasPublicId;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'public_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'waiting',
        'is_emergency' => false,
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
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    /**
     * @return HasMany<VisitItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(VisitItem::class);
    }

    /**
     * @return HasMany<AppointmentItem, $this>
     */
    public function appointmentItems(): HasMany
    {
        return $this->hasMany(AppointmentItem::class);
    }

    /**
     * @return HasOne<MedicalEntry, $this>
     */
    public function medicalEntry(): HasOne
    {
        return $this->hasOne(MedicalEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_group' => ServiceGroup::class,
            'visit_date' => 'date',
            'queue_no' => 'integer',
            'status' => VisitStatus::class,
            'is_emergency' => 'boolean',
            'priority_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
