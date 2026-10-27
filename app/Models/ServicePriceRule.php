<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PetSize;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ServicePriceRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Duration / price override; most specific wins: breed → species + size → species → service.
 */
class ServicePriceRule extends Model
{
    /** @use HasFactory<ServicePriceRuleFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => PetSize::class,
            'duration_min' => 'integer',
            'price' => 'decimal:2',
        ];
    }
}
