<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VaccineKind;
use App\Tenancy\BelongsToTenant;
use Database\Factories\VaccineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccine extends Model
{
    /** @use HasFactory<VaccineFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Species, $this>
     */
    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => VaccineKind::class,
        ];
    }
}
