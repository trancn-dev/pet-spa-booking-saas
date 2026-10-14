<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Makes HTTP tests behave like a request to a tenant's own host
 * ({slug}.{central domain}) and creates users inside that tenant.
 *
 * Picked up by Laravel's `setUp{TraitName}` convention.
 */
trait ActsOnTenantHost
{
    protected Tenant $tenant;

    protected function setUpActsOnTenantHost(): void
    {
        $this->withoutVite();

        $this->tenant = Tenant::factory()->create(['slug' => 'acme']);

        $this->useTenantHost($this->tenant);
    }

    protected function useTenantHost(Tenant $tenant): void
    {
        $url = 'http://'.$tenant->slug.'.'.config('tenancy.central_domain');

        config(['app.url' => $url]);
        URL::forceRootUrl($url);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function tenantUser(array $attributes = []): User
    {
        return User::factory()->for($this->tenant)->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function unverifiedTenantUser(array $attributes = []): User
    {
        return User::factory()->for($this->tenant)->unverified()->create($attributes);
    }
}
