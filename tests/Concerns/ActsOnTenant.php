<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Creates a tenant (`acme`) and users that are active staff members of it.
 * Tenant routes live at `/app/acme/...`; users themselves are global.
 *
 * Picked up by Laravel's `setUp{TraitName}` convention.
 */
trait ActsOnTenant
{
    protected Tenant $tenant;

    protected function setUpActsOnTenant(): void
    {
        $this->withoutVite();

        $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    }

    protected function makeMember(User $user, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;

        app(TenantContext::class)->runAs($tenant, fn () => Staff::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'display_name' => $user->name,
        ]));

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function tenantUser(array $attributes = []): User
    {
        return $this->makeMember(User::factory()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function unverifiedTenantUser(array $attributes = []): User
    {
        return $this->makeMember(User::factory()->unverified()->create($attributes));
    }
}
