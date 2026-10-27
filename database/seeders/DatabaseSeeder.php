<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Minimal local data: one shop, one branch, one member. The full demo dataset
 * (design.md section 9) is task 3.2 (ReferenceDataSeeder + DemoSeeder).
 *
 * No WithoutModelEvents here: BelongsToTenant stamps tenant_id in a model event.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::query()->firstOrCreate(['code' => 'pro'], [
            'name' => 'Pro',
            'max_branches' => 3,
            'max_staff' => 10,
            'max_appointments_per_month' => 500,
        ]);

        $tenant = Tenant::factory()->create([
            'name' => 'Demo Pet Spa',
            'slug' => 'demo',
            'plan_id' => $plan->id,
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        app(TenantContext::class)->runAs($tenant, function () use ($user): void {
            TenantSetting::query()->create();

            $branch = Branch::factory()->create(['code' => 'CS1', 'name' => 'Cơ sở chính']);

            Staff::factory()->create([
                'user_id' => $user->id,
                'primary_branch_id' => $branch->id,
                'display_name' => $user->name,
            ]);
        });
    }
}
