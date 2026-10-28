<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Models\Pet;
use App\Models\Service;
use App\Models\ServicePriceRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Duration and price of a service for a pet (db-schema 2.4): the most specific matching rule wins,
 * breed → species + size → species → the service's defaults. A rule may set only the duration or
 * only the price; the other value then comes from the next, more general match.
 *
 * One query: every rule that matches, ordered by specificity (breed 4, species 2, size 1).
 */
class ServiceQuoteResolver
{
    public function resolve(Service $service, Pet|PetProfile $pet): Quote
    {
        $profile = $pet instanceof Pet ? PetProfile::fromPet($pet) : $pet;

        $rules = $this->matching($service, $profile);

        $durationRule = $rules->first(fn (ServicePriceRule $r) => $r->duration_min !== null);
        $priceRule = $rules->first(fn (ServicePriceRule $r) => $r->price !== null);

        $duration = $durationRule->duration_min ?? $service->duration_min;
        $price = $priceRule !== null ? (string) $priceRule->price : (string) $service->price;

        // The rule shown to people: the most specific one that changed something.
        $applied = $rules->first(fn (ServicePriceRule $r) => $r->is($durationRule) || $r->is($priceRule));

        return new Quote(
            durationMin: (int) $duration,
            bufferMin: (int) $service->buffer_min,
            price: $price,
            appliedRule: $applied,
            explanation: $duration.' phút · '.($applied === null ? 'mặc định của dịch vụ' : 'áp dụng cho '.self::label($applied)),
        );
    }

    /**
     * "Chó cỡ L", "Poodle", "Poodle cỡ S", "Mèo", "cỡ XL". Uses the joined `breed_name` /
     * `species_name` of resolve(), or the `breed` / `species` relations when loaded.
     */
    public static function label(ServicePriceRule $rule): string
    {
        $joined = $rule->getAttributes(); // strict mode: never read an attribute that was not selected
        $breed = $joined['breed_name'] ?? ($rule->relationLoaded('breed') ? $rule->breed?->name : null);
        $species = $joined['species_name'] ?? ($rule->relationLoaded('species') ? $rule->species?->name : null);
        $who = $breed ?? $species;
        $size = $rule->size === null ? null : 'cỡ '.strtoupper($rule->size->value);

        return trim(($who ?? '').' '.($size ?? '')) ?: 'mọi pet';
    }

    /**
     * @return Collection<int, ServicePriceRule>
     */
    private function matching(Service $service, PetProfile $profile): Collection
    {
        $t = 'service_price_rules';

        return ServicePriceRule::query()
            ->select("{$t}.*", 'species.name as species_name', 'breeds.name as breed_name')
            ->leftJoin('species', 'species.id', '=', "{$t}.species_id")
            ->leftJoin('breeds', 'breeds.id', '=', "{$t}.breed_id")
            ->where("{$t}.service_id", $service->getKey())
            ->where(fn (Builder $q) => $q->whereNull("{$t}.breed_id")->when($profile->breedId !== null, fn (Builder $q) => $q->orWhere("{$t}.breed_id", $profile->breedId)))
            ->where(fn (Builder $q) => $q->whereNull("{$t}.species_id")->when($profile->speciesId !== null, fn (Builder $q) => $q->orWhere("{$t}.species_id", $profile->speciesId)))
            ->where(fn (Builder $q) => $q->whereNull("{$t}.size")->when($profile->size !== null, fn (Builder $q) => $q->orWhere("{$t}.size", $profile->size?->value)))
            ->orderByRaw("(CASE WHEN {$t}.breed_id IS NULL THEN 0 ELSE 4 END) + (CASE WHEN {$t}.species_id IS NULL THEN 0 ELSE 2 END) + (CASE WHEN {$t}.size IS NULL THEN 0 ELSE 1 END) DESC")
            ->orderBy("{$t}.id")
            ->get();
    }
}
