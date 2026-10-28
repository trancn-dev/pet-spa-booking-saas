<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\PetSize;
use App\Models\Breed;
use App\Models\Service;
use App\Models\ServicePriceRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A duration / price rule of a service by species, breed, size (db-schema 2.4).
 *
 * UNIQUE(tenant_id, service_id, species_id, breed_id, size) does not stop duplicates when a
 * column is NULL (NULL ≠ NULL in MySQL), so the check runs here, under the service row lock.
 */
class SavePriceRule
{
    public const DUPLICATE = 'Đã có quy tắc cho đúng nhóm pet này.';

    /**
     * @param  array{species_id: int|null, breed_id: int|null, size: string|null, duration_min: int|null, price: string|null}  $data
     */
    public function handle(Service $service, ?ServicePriceRule $rule, array $data): ServicePriceRule
    {
        $breed = $data['breed_id'] !== null ? Breed::query()->find($data['breed_id']) : null;
        $speciesId = $data['species_id'] ?? $breed?->species_id;
        $size = $data['size'] !== null ? PetSize::from($data['size']) : null;

        if ($breed !== null && $breed->species_id !== $speciesId) {
            throw ValidationException::withMessages(['breed_id' => 'Giống không thuộc loài đã chọn.']);
        }
        if ($speciesId === null && $size === null) {
            throw ValidationException::withMessages(['species_id' => 'Chọn loài, giống hoặc cỡ cho quy tắc.']);
        }
        if ($data['duration_min'] === null && $data['price'] === null) {
            throw ValidationException::withMessages(['duration_min' => 'Nhập thời lượng hoặc giá (hoặc cả hai).']);
        }

        return DB::transaction(function () use ($service, $rule, $speciesId, $breed, $size, $data): ServicePriceRule {
            Service::query()->whereKey($service->getKey())->lockForUpdate()->first();

            $duplicate = ServicePriceRule::query()->where('service_id', $service->getKey())
                ->when($rule !== null, fn ($q) => $q->whereKeyNot($rule?->getKey()))
                ->when($speciesId === null, fn ($q) => $q->whereNull('species_id'), fn ($q) => $q->where('species_id', $speciesId))
                ->when($breed === null, fn ($q) => $q->whereNull('breed_id'), fn ($q) => $q->where('breed_id', $breed?->id))
                ->when($size === null, fn ($q) => $q->whereNull('size'), fn ($q) => $q->where('size', $size?->value))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['species_id' => self::DUPLICATE]);
            }

            $attributes = [
                'species_id' => $speciesId,
                'breed_id' => $breed?->id,
                'size' => $size,
                'duration_min' => $data['duration_min'],
                'price' => $data['price'],
            ];

            if ($rule === null) {
                return $service->priceRules()->create($attributes);
            }

            $rule->update($attributes);

            return $rule;
        });
    }
}
