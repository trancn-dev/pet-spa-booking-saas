<?php

declare(strict_types=1);

use App\Enums\PlatformAdminRole;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Platform\PlatformAdmin;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| A2: shop list with aggregate numbers only, a fixed number of queries.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->super = PlatformAdmin::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->support = PlatformAdmin::factory()->create(['role' => PlatformAdminRole::Support, 'two_factor_confirmed_at' => now()]);

    $this->makeShop = function (string $name, TenantStatus $status, int $branches, int $staff, ?CarbonInterface $expires = null): Tenant {
        $tenant = Tenant::factory()->create(['name' => $name, 'status' => $status, 'plan_expires_at' => $expires, 'contact_name' => "Chủ {$name}"]);

        inTenant($tenant, function () use ($branches, $staff) {
            $first = Branch::factory()->create();
            Branch::factory()->count($branches - 1)->create();
            Staff::factory()->count($staff)->create(['primary_branch_id' => $first->id]);
        });

        DB::table('tenant_usage')->insert(['tenant_id' => $tenant->id, 'period' => now()->format('Y-m'), 'appointments_count' => 42]);

        return $tenant;
    };
});

it('shows an empty state when there is no shop yet', function () {
    $this->actingAs($this->super, 'platform')->get('/admin/tenants')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Tenants/Index')
            ->where('kpis.total', 0)
            ->has('tenants.data', 0));
});

it('lists shops with plan, expiry, status and counts, plus KPIs', function () {
    ($this->makeShop)('Paw House', TenantStatus::Active, 2, 3, now()->addYear());
    ($this->makeShop)('Happy Tails', TenantStatus::Trial, 1, 1, now()->addDays(5));
    ($this->makeShop)('Bé Cún', TenantStatus::Suspended, 1, 1, now()->subDays(10));

    $this->actingAs($this->super, 'platform')->get('/admin/tenants')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('kpis', ['total' => 3, 'active' => 2, 'expiring' => 1, 'overdue' => 1])
            ->has('tenants.data', 3)
            ->where('tenants.data.2.name', 'Paw House')
            ->where('tenants.data.2.branches_count', 2)
            ->where('tenants.data.2.staff_count', 3)
            ->where('tenants.data.2.appointments_this_month', 42)
            ->where('tenants.data.2.owner_name', 'Chủ Paw House')
            ->where('tenants.data.2.status', 'active')
            ->where('can.createTenant', true));
});

it('filters by search text and status', function () {
    ($this->makeShop)('Paw House', TenantStatus::Active, 1, 1);
    ($this->makeShop)('Meow Clinic', TenantStatus::PastDue, 1, 1);

    $this->actingAs($this->super, 'platform')->get('/admin/tenants?q=meow')
        ->assertInertia(fn (Assert $page) => $page->has('tenants.data', 1)->where('tenants.data.0.name', 'Meow Clinic'));

    $this->get('/admin/tenants?status=active')
        ->assertInertia(fn (Assert $page) => $page->has('tenants.data', 1)->where('tenants.data.0.name', 'Paw House'));
});

it('runs the same number of queries for 2 shops as for 6 (no N+1)', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->super, 'platform')->get('/admin/tenants')->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    ($this->makeShop)('A', TenantStatus::Active, 1, 1);
    ($this->makeShop)('B', TenantStatus::Active, 2, 2);
    $few = $count();

    foreach (['C', 'D', 'E', 'F'] as $name) {
        ($this->makeShop)($name, TenantStatus::Active, 2, 2);
    }

    expect($count())->toBe($few);
});

it('does not offer shop creation to support admins', function () {
    $this->actingAs($this->support, 'platform')->get('/admin/tenants')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.createTenant', false)->where('admin.is_super_admin', false));
});
