<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Species;
use App\Support\ActivityLog;
use App\Support\PetSizeSuggester;
use Illuminate\Validation\ValidationException;

/**
 * Adds or edits a pet, always inside its customer (S4 / S10). With no size chosen the suggestion
 * applies (breed default or weight). Changes to the safety alerts are logged.
 */
class SavePet
{
    private const ALERTS = ['allergies', 'chronic_conditions', 'behavior_notes'];

    public function __construct(private readonly PetSizeSuggester $sizes) {}

    /**
     * @param  array{name: string, species_id: int, breed_id?: int|null, sex?: string|null, birth_date?: string|null, weight_kg?: string|float|null, size?: string|null, is_neutered?: bool, allergies?: string|null, chronic_conditions?: string|null, behavior_notes?: string|null}  $data
     */
    public function handle(Customer $customer, ?Pet $pet, array $data): Pet
    {
        $breed = isset($data['breed_id']) ? Breed::query()->with('species:id,code')->find($data['breed_id']) : null;
        if ($breed !== null && $breed->species_id !== (int) $data['species_id']) {
            throw ValidationException::withMessages(['breed_id' => 'Giống không thuộc loài đã chọn.']);
        }

        $size = isset($data['size']) && $data['size'] !== '' ? PetSize::from($data['size']) : null;
        if ($size === null) {
            $speciesCode = $breed?->species->code ?? Species::query()->whereKey($data['species_id'])->value('code');
            $size = $this->sizes->suggest(is_string($speciesCode) ? $speciesCode : null, $breed?->default_size, $breed?->name, $data['weight_kg'] ?? null)['size'] ?? null;
        }
        if ($size === null) {
            throw ValidationException::withMessages(['size' => 'Chọn cỡ (hoặc nhập cân nặng để gợi ý).']);
        }

        $attributes = [
            'name' => trim($data['name']),
            'species_id' => (int) $data['species_id'],
            'breed_id' => $breed?->id,
            'sex' => PetSex::tryFrom((string) ($data['sex'] ?? '')) ?? PetSex::Unknown,
            'birth_date' => self::blank($data['birth_date'] ?? null),
            'weight_kg' => isset($data['weight_kg']) && $data['weight_kg'] !== '' ? $data['weight_kg'] : null,
            'size' => $size,
            'is_neutered' => (bool) ($data['is_neutered'] ?? false),
            'allergies' => self::blank($data['allergies'] ?? null),
            'chronic_conditions' => self::blank($data['chronic_conditions'] ?? null),
            'behavior_notes' => self::blank($data['behavior_notes'] ?? null),
        ];

        if ($pet === null) {
            $pet = $customer->pets()->create($attributes + ['is_stray' => $customer->is_system]);
            $alerts = array_filter(array_intersect_key($attributes, array_flip(self::ALERTS)));
            if ($alerts !== []) {
                ActivityLog::record('pet.alerts_changed', $pet, $alerts);
            }

            return $pet;
        }

        $pet->fill($attributes);
        $alerts = array_intersect_key($pet->getDirty(), array_flip(self::ALERTS));
        $pet->save();
        if ($alerts !== []) {
            ActivityLog::record('pet.alerts_changed', $pet, $alerts);
        }

        return $pet;
    }

    private static function blank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
