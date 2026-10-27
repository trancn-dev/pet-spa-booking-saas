<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Reference data everywhere; the demo dataset (design.md section 9) only outside production.
 *
 * Seeders must not use WithoutModelEvents: BelongsToTenant stamps tenant_id in a model event.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment(['local', 'demo', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
