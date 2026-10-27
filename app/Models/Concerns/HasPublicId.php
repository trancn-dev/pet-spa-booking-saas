<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * `public_id` CHAR(26) ULID for anything exposed in a URL, so sequential ids cannot be guessed.
 * The numeric `id` stays the primary key; routes bind on `public_id`.
 */
trait HasPublicId
{
    use HasUlids;

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
