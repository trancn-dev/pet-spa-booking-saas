<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PetSize;

/**
 * Suggests a pet's size (prices and durations depend on it, db-schema 2.4). A known weight wins,
 * otherwise the breed's default size. Always says why (design 10, rule 3).
 */
final class PetSizeSuggester
{
    /** Upper weight bound (kg, exclusive) per size; above the last one is XL. */
    private const THRESHOLDS = [
        'cat' => ['xs' => 2.5, 's' => 4.5, 'm' => 6.5, 'l' => 9.0],
        'default' => ['xs' => 5.0, 's' => 10.0, 'm' => 25.0, 'l' => 40.0],
    ];

    /**
     * @return array{size: PetSize, reason: string}|null
     */
    public function suggest(?string $speciesCode, ?PetSize $breedDefault, ?string $breedName, float|string|null $weightKg): ?array
    {
        $weight = is_numeric($weightKg) ? (float) $weightKg : null;

        if ($weight !== null && $weight > 0) {
            $size = $this->byWeight($speciesCode, $weight);

            return ['size' => $size, 'reason' => 'Theo cân nặng '.$this->kg($weight).' kg'];
        }

        if ($breedDefault !== null) {
            return ['size' => $breedDefault, 'reason' => 'Cỡ thường gặp của giống '.($breedName ?? 'này')];
        }

        return null;
    }

    private function byWeight(?string $speciesCode, float $weight): PetSize
    {
        $bounds = self::THRESHOLDS[$speciesCode] ?? self::THRESHOLDS['default'];
        foreach ($bounds as $size => $max) {
            if ($weight < $max) {
                return PetSize::from($size);
            }
        }

        return PetSize::Xl;
    }

    private function kg(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2, ',', ''), '0'), ',');
    }
}
