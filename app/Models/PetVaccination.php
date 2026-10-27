<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VaccinationStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\PetVaccinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vaccination book entry; `postponed` rows carry the new date in next_due_on.
 */
class PetVaccination extends Model
{
    /** @use HasFactory<PetVaccinationFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Pet, $this>
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Vaccine, $this>
     */
    public function vaccine(): BelongsTo
    {
        return $this->belongsTo(Vaccine::class);
    }

    /**
     * @return BelongsTo<CareProtocol, $this>
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(CareProtocol::class, 'protocol_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'administered_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'administered_on' => 'date',
            'batch_expires_on' => 'date',
            'next_due_on' => 'date',
            'superseded_at' => 'datetime',
            'status' => VaccinationStatus::class,
        ];
    }
}
