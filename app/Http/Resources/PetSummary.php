<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pet;
use Carbon\CarbonInterface;

/**
 * How a pet appears wherever it is listed. The safety alerts (allergies, chronic conditions,
 * behaviour: "cắn người", "sợ máy sấy") always travel with it (design 10, rule 6).
 */
final class PetSummary
{
    /**
     * @return list<array{kind: string, label: string, text: string}>
     */
    public static function alerts(Pet $pet): array
    {
        $alerts = [];
        foreach (['allergies' => 'Dị ứng', 'chronic_conditions' => 'Bệnh nền', 'behavior_notes' => 'Lưu ý'] as $field => $label) {
            $text = $pet->getAttribute($field);
            if (is_string($text) && trim($text) !== '') {
                $alerts[] = ['kind' => $field, 'label' => $label, 'text' => trim($text)];
            }
        }

        return $alerts;
    }

    /**
     * Needs `species` and `breed` loaded.
     *
     * @return array<string, mixed>
     */
    public static function make(Pet $pet): array
    {
        return [
            'id' => $pet->public_id,
            'name' => $pet->name,
            'species' => $pet->species->name,
            'breed' => $pet->breed?->name,
            'sex' => $pet->sex->value,
            'sex_label' => $pet->sex->label(),
            'size' => $pet->size->value,
            'size_label' => $pet->size->label(),
            'age' => self::age($pet->birth_date),
            'weight_kg' => $pet->weight_kg,
            'is_neutered' => $pet->is_neutered,
            'is_stray' => $pet->is_stray,
            'alerts' => self::alerts($pet),
        ];
    }

    /** "2 tuổi 3 tháng", "5 tháng", "Dưới 1 tháng"; null when unknown. */
    public static function age(?CarbonInterface $birthDate, ?CarbonInterface $on = null): ?string
    {
        if ($birthDate === null) {
            return null;
        }

        $months = (int) $birthDate->diffInMonths($on ?? now(), true);
        if ($months < 1) {
            return 'Dưới 1 tháng';
        }

        $years = intdiv($months, 12);
        $rest = $months % 12;

        return trim(($years > 0 ? $years.' tuổi ' : '').($rest > 0 ? $rest.' tháng' : ''));
    }
}
