<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Demo Pet Spa', 'slug' => 'demo']);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        app(TenantContext::class)->runAs($tenant, fn () => Staff::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'display_name' => $user->name,
        ]));
    }
}
