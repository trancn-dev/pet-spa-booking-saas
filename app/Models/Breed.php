<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PetSize;
use Database\Factories\BreedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared catalogue.
 */
class Breed extends Model
{
    /** @use HasFactory<BreedFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

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
            'default_size' => PetSize::class,
        ];
    }
}
