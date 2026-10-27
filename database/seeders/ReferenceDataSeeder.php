<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Plan;
use App\Models\Species;
use Illuminate\Database\Seeder;

/**
 * Platform reference data for every environment (production included). Idempotent: upserts.
 */
class ReferenceDataSeeder extends Seeder
{
    /** NULL limit = unlimited. */
    public const PLANS = [
        ['code' => 'starter', 'name' => 'Starter', 'max_branches' => 1, 'max_staff' => 3, 'max_appointments_per_month' => 150],
        ['code' => 'pro', 'name' => 'Pro', 'max_branches' => 3, 'max_staff' => 10, 'max_appointments_per_month' => 500],
        ['code' => 'clinic', 'name' => 'Clinic', 'max_branches' => null, 'max_staff' => null, 'max_appointments_per_month' => null],
    ];

    /** species code => [name, [breed => default size]] */
    public const BREEDS = [
        'dog' => ['Chó', [
            'Chó ta' => 'm',
            'Poodle' => 's',
            'Phốc sóc (Pomeranian)' => 'xs',
            'Chihuahua' => 'xs',
            'Pug' => 's',
            'Shih Tzu' => 's',
            'Corgi' => 'm',
            'Beagle' => 'm',
            'Golden Retriever' => 'l',
            'Husky' => 'l',
            'Becgie (German Shepherd)' => 'l',
            'Alaska Malamute' => 'xl',
        ]],
        'cat' => ['Mèo', [
            'Mèo ta' => 's',
            'Mèo Xiêm' => 's',
            'Mèo Munchkin' => 's',
            'Mèo Anh lông ngắn' => 'm',
            'Mèo Ba Tư' => 'm',
        ]],
    ];

    public function run(): void
    {
        Plan::query()->upsert(
            array_map(fn (array $plan): array => [...$plan, 'features' => null], self::PLANS),
            ['code'],
            ['name', 'max_branches', 'max_staff', 'max_appointments_per_month'],
        );

        Species::query()->upsert(
            array_map(fn (string $code, array $s): array => ['code' => $code, 'name' => $s[0]], array_keys(self::BREEDS), self::BREEDS),
            ['code'],
            ['name'],
        );

        $speciesIds = Species::query()->pluck('id', 'code');

        $breeds = [];
        foreach (self::BREEDS as $code => [, $list]) {
            foreach ($list as $name => $size) {
                $breeds[] = ['species_id' => $speciesIds[$code], 'name' => $name, 'default_size' => $size];
            }
        }

        Breed::query()->upsert($breeds, ['species_id', 'name'], ['default_size']);
    }
}
