<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pet;
use Illuminate\Support\Facades\DB;

/**
 * "Thú hoang #n": a pet with no owner, attached to the branch's system profile (db-schema 2.3).
 * Numbers count every stray the branch ever had, deleted ones included, so they are never reused.
 */
class CreateStrayPet
{
    public function handle(Branch $branch, int $speciesId, ?string $note = null): Pet
    {
        return DB::transaction(function () use ($branch, $speciesId, $note): Pet {
            $profile = Customer::query()->where('is_system', true)->where('system_branch_id', $branch->getKey())
                ->lockForUpdate()->firstOrFail();

            $n = $profile->pets()->withTrashed()->count() + 1;

            return $profile->pets()->create([
                'name' => 'Thú hoang #'.$n,
                'species_id' => $speciesId,
                'sex' => PetSex::Unknown,
                'size' => PetSize::M,
                'is_stray' => true,
                'behavior_notes' => $note === null || trim($note) === '' ? null : trim($note),
            ]);
        });
    }
}
