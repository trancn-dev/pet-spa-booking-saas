<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

/**
 * For queued jobs that work on one tenant's data. The tenant of the dispatching context is
 * written into the payload (`tenant_id`, see AppServiceProvider) and restored before handle().
 * A TenantAware job dispatched with no tenant context fails instead of running unscoped.
 *
 * If the job defines its own middleware(), it must include `new SetTenantFromPayload`.
 */
trait TenantAware
{
    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SetTenantFromPayload];
    }
}
