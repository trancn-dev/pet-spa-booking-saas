<?php

declare(strict_types=1);

use App\Enums\PetSize;
use App\Support\PetSizeSuggester;

it('suggests a dog size from its weight', function (float $kg, PetSize $size) {
    expect((new PetSizeSuggester)->suggest('dog', null, null, $kg)['size'] ?? null)->toBe($size);
})->with([
    [2.0, PetSize::Xs], [4.99, PetSize::Xs], [5.0, PetSize::S], [9.9, PetSize::S],
    [10.0, PetSize::M], [24.9, PetSize::M], [25.0, PetSize::L], [39.9, PetSize::L], [40.0, PetSize::Xl], [60.0, PetSize::Xl],
]);

it('uses cat bounds for cats', function (float $kg, PetSize $size) {
    expect((new PetSizeSuggester)->suggest('cat', null, null, $kg)['size'] ?? null)->toBe($size);
})->with([
    [2.0, PetSize::Xs], [3.5, PetSize::S], [5.0, PetSize::M], [7.0, PetSize::L], [10.0, PetSize::Xl],
]);

it('prefers the weight over the breed and says why', function () {
    $hint = (new PetSizeSuggester)->suggest('dog', PetSize::L, 'Golden Retriever', '12.5');

    expect($hint)->toBe(['size' => PetSize::M, 'reason' => 'Theo cân nặng 12,5 kg']);
});

it('falls back to the breed default without a weight', function () {
    $hint = (new PetSizeSuggester)->suggest('dog', PetSize::Xs, 'Chihuahua', null);

    expect($hint)->toBe(['size' => PetSize::Xs, 'reason' => 'Cỡ thường gặp của giống Chihuahua']);
});

it('ignores a zero or non-numeric weight and has nothing to say without data', function () {
    $sizes = new PetSizeSuggester;

    expect($sizes->suggest('dog', null, null, 0))->toBeNull()
        ->and($sizes->suggest('dog', null, null, 'abc'))->toBeNull()
        ->and($sizes->suggest(null, null, null, null))->toBeNull()
        ->and($sizes->suggest('rabbit', null, null, 3)['size'] ?? null)->toBe(PetSize::Xs);
});
