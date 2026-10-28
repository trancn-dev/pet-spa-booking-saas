<?php

declare(strict_types=1);

use App\Enums\PetSize;
use App\Models\Breed;
use App\Models\Pet;
use App\Models\Service;
use App\Models\ServicePriceRule;
use App\Models\Species;
use App\Models\Tenant;
use App\Pricing\PetProfile;
use App\Pricing\ServiceQuoteResolver;
use Illuminate\Support\Facades\DB;

/*
| Task 5.2: duration / price by pet. Most specific wins: breed → species + size → species →
| the service default; a rule may set only one of duration / price.
*/

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    $this->cat = Species::query()->firstOrCreate(['code' => 'cat'], ['name' => 'Mèo']);
    $this->poodle = Breed::query()->create(['species_id' => $this->dog->id, 'name' => 'Poodle', 'default_size' => PetSize::S]);
    $this->husky = Breed::query()->create(['species_id' => $this->dog->id, 'name' => 'Husky', 'default_size' => PetSize::L]);
    $this->service = inTenant($this->tenant, fn () => Service::factory()->create(['duration_min' => 60, 'buffer_min' => 15, 'price' => '200000.00']));
    $this->rule = fn (array $a) => inTenant($this->tenant, fn () => ServicePriceRule::factory()->create([
        'service_id' => $this->service->id, 'species_id' => null, 'breed_id' => null, 'size' => null, 'duration_min' => null, 'price' => null, ...$a,
    ]));
    $this->quote = fn (?int $species, ?int $breed, ?PetSize $size) => inTenant($this->tenant, fn () => app(ServiceQuoteResolver::class)->resolve($this->service, new PetProfile($species, $breed, $size)));
});

it('uses the service defaults without rules', function () {
    $q = ($this->quote)($this->dog->id, null, PetSize::M);

    expect($q->durationMin)->toBe(60)->and($q->bufferMin)->toBe(15)->and($q->price)->toBe('200000.00')
        ->and($q->appliedRule)->toBeNull()
        ->and($q->explanation)->toBe('60 phút · mặc định của dịch vụ')
        ->and($q->blockedMin())->toBe(75);
});

it('applies a species rule, and not to another species', function () {
    ($this->rule)(['species_id' => $this->dog->id, 'duration_min' => 70, 'price' => '250000.00']);

    expect(($this->quote)($this->dog->id, null, PetSize::M)->explanation)->toBe('70 phút · áp dụng cho Chó')
        ->and(($this->quote)($this->cat->id, null, PetSize::M)->durationMin)->toBe(60);
});

it('prefers species + size over species', function () {
    ($this->rule)(['species_id' => $this->dog->id, 'duration_min' => 70]);
    ($this->rule)(['species_id' => $this->dog->id, 'size' => PetSize::L, 'duration_min' => 90, 'price' => '350000.00']);

    $large = ($this->quote)($this->dog->id, null, PetSize::L);
    $medium = ($this->quote)($this->dog->id, null, PetSize::M);

    expect($large->durationMin)->toBe(90)->and($large->price)->toBe('350000.00')
        ->and($large->explanation)->toBe('90 phút · áp dụng cho Chó cỡ L')
        ->and($medium->durationMin)->toBe(70);
});

it('prefers the breed over species + size, and breed + size over the breed', function () {
    ($this->rule)(['species_id' => $this->dog->id, 'size' => PetSize::L, 'duration_min' => 90]);
    ($this->rule)(['species_id' => $this->dog->id, 'breed_id' => $this->husky->id, 'duration_min' => 120]);
    ($this->rule)(['species_id' => $this->dog->id, 'breed_id' => $this->husky->id, 'size' => PetSize::Xl, 'duration_min' => 150]);

    expect(($this->quote)($this->dog->id, $this->husky->id, PetSize::L)->explanation)->toBe('120 phút · áp dụng cho Husky')
        ->and(($this->quote)($this->dog->id, $this->husky->id, PetSize::Xl)->explanation)->toBe('150 phút · áp dụng cho Husky cỡ XL')
        // another breed, or no breed: back to species + size
        ->and(($this->quote)($this->dog->id, $this->poodle->id, PetSize::L)->durationMin)->toBe(90)
        ->and(($this->quote)($this->dog->id, null, PetSize::L)->durationMin)->toBe(90);
});

it('takes each value from the most specific rule that sets it', function () {
    ($this->rule)(['species_id' => $this->dog->id, 'duration_min' => 70, 'price' => '250000.00']);
    ($this->rule)(['species_id' => $this->dog->id, 'breed_id' => $this->poodle->id, 'price' => '280000.00']); // price only

    $q = ($this->quote)($this->dog->id, $this->poodle->id, PetSize::S);

    expect($q->durationMin)->toBe(70)->and($q->price)->toBe('280000.00')
        ->and($q->appliedRule?->breed_id)->toBe($this->poodle->id)
        ->and($q->explanation)->toBe('70 phút · áp dụng cho Poodle');
});

it('applies a size-only rule to any species, below species rules', function () {
    ($this->rule)(['size' => PetSize::Xl, 'duration_min' => 100]);
    ($this->rule)(['species_id' => $this->cat->id, 'duration_min' => 40]);

    expect(($this->quote)($this->dog->id, null, PetSize::Xl)->explanation)->toBe('100 phút · áp dụng cho cỡ XL')
        ->and(($this->quote)($this->cat->id, null, PetSize::Xl)->durationMin)->toBe(40);
});

it('resolves a saved pet like its profile, in one query', function () {
    ($this->rule)(['species_id' => $this->dog->id, 'size' => PetSize::L, 'duration_min' => 90]);
    ($this->rule)(['species_id' => $this->cat->id, 'duration_min' => 40]);
    $pet = inTenant($this->tenant, fn () => Pet::factory()->create(['species_id' => $this->dog->id, 'size' => PetSize::L]));

    DB::flushQueryLog();
    DB::enableQueryLog();
    $q = inTenant($this->tenant, fn () => app(ServiceQuoteResolver::class)->resolve($this->service, $pet));

    expect($q->durationMin)->toBe(90)->and(DB::getQueryLog())->toHaveCount(1);
});

it('never reads another shop rules', function () {
    $other = Tenant::factory()->create();
    inTenant($other, fn () => ServicePriceRule::factory()->create(['service_id' => Service::factory()->create()->id, 'species_id' => $this->dog->id, 'size' => null, 'duration_min' => 5]));

    expect(($this->quote)($this->dog->id, null, PetSize::M)->durationMin)->toBe(60);
});
