<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\MedicalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One exam line. internal_notes is never shown on the Site; editable by its author for 24 h.
 */
class MedicalEntry extends Model
{
    /** @use HasFactory<MedicalEntryFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<MedicalRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_staff_id');
    }

    /**
     * @return HasMany<MedicalEntryAddendum, $this>
     */
    public function addenda(): HasMany
    {
        return $this->hasMany(MedicalEntryAddendum::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vitals' => 'array',
            'follow_up_on' => 'date',
            'recorded_at' => 'datetime',
            'editable_until' => 'datetime',
        ];
    }
}
