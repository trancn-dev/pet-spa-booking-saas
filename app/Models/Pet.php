<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Models\Concerns\HasPublicId;
use App\Tenancy\BelongsToTenant;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * allergies / chronic_conditions / behavior_notes are safety alerts shown to every role.
 *
 * @property PetSex $sex
 * @property PetSize $size
 */
class Pet extends Model
{
    /** @use HasFactory<PetFactory> */
    use BelongsToTenant, HasFactory, HasPublicId, SoftDeletes;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id', 'public_id'];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return BelongsTo<Breed, $this>
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /**
     * @return HasOne<MedicalRecord, $this>
     */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /**
     * @return HasMany<PetVaccination, $this>
     */
    public function vaccinations(): HasMany
    {
        return $this->hasMany(PetVaccination::class);
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => PetSex::class,
            'size' => PetSize::class,
            'birth_date' => 'date',
            'weight_kg' => 'decimal:2',
            'is_neutered' => 'boolean',
            'is_stray' => 'boolean',
        ];
    }
}
