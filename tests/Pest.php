<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsOnTenant;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Arch');
pest()->extend(TestCase::class)->in('Unit');
pest()->use(ActsOnTenant::class)->in('Feature/ExampleTest.php');

/**
 * Run a callback as the given tenant (factories for tenant-owned models need a tenant context).
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->runAs($tenant, $callback);
}
