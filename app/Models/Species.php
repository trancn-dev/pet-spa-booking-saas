<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SpeciesFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shared catalogue (dog, cat).
 */
class Species extends Model
{
    /** @use HasFactory<SpeciesFactory> */
    use HasFactory;

    protected $table = 'species';

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return HasMany<Breed, $this>
     */
    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
