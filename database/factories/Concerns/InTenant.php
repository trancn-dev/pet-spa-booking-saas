<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-aware factory defaults. Use factories inside `TenantContext::runAs($tenant, ...)`
 * (or `withoutTenancy()` with an explicit tenant_id): tenant-owned models refuse to be
 * created with no tenant context.
 */
trait InTenant
{
    /**
     * tenant_id default: the current tenant; outside a tenant (withoutTenancy), a new one.
     */
    protected function currentTenant(): Closure
    {
        return fn () => app(TenantContext::class)->id() ?? Tenant::factory();
    }

    /**
     * A parent record created in the same tenant as this one.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $state
     */
    protected function sameTenant(string $model, array $state = []): Closure
    {
        return fn (array $attributes) => Factory::factoryForModel($model)
            ->state(['tenant_id' => $attributes['tenant_id'], ...$state]);
    }
}
