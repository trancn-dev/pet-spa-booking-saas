<?php

declare(strict_types=1);

namespace App\Tenancy\Jobs;

use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Closure;
use Illuminate\Contracts\Queue\Job;

/**
 * Job middleware: runs the job inside the tenant recorded in its payload.
 */
class SetTenantFromPayload
{
    public function handle(object $job, Closure $next): mixed
    {
        $queueJob = property_exists($job, 'job') ? $job->job : null;
        $payload = $queueJob instanceof Job ? $queueJob->payload() : [];
        $tenantId = $payload['tenant_id'] ?? null;

        if ($tenantId === null) {
            throw new TenantNotResolvedException('TenantAware job ['.$job::class.'] was dispatched without a tenant context.');
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            throw new TenantNotResolvedException('TenantAware job ['.$job::class.']: tenant ['.$tenantId.'] no longer exists.');
        }

        return app(TenantContext::class)->runAs($tenant, fn () => $next($job));
    }
}
