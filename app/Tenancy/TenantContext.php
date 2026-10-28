<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant for the current request / job. Registered as a scoped singleton,
 * so it is reset between Octane requests and queue jobs.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $bypassed = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * The current tenant, or an exception: for code that can only run inside a tenant.
     */
    public function require(): Tenant
    {
        return $this->tenant ?? throw new TenantNotResolvedException('No current tenant.');
    }

    public function id(): ?int
    {
        $key = $this->tenant?->getKey();

        return $key === null ? null : (int) $key;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Run a callback as the given tenant, restoring the previous context afterwards.
     * The callback always sees tenant scoping, even when called from inside withoutTenancy().
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $previousBypass = $this->bypassed;
        $this->tenant = $tenant;
        $this->bypassed = false;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
            $this->bypassed = $previousBypass;
        }
    }

    /**
     * Explicit, greppable escape hatch for cross-tenant work (platform admin, scheduler scans).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenancy(Closure $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }

    /**
     * Cache / lock / rate-limiter key namespaced by the current tenant: `t:{tenant_id}:{key}`.
     */
    public function key(string $key): string
    {
        return 't:'.$this->require()->getKey().':'.$key;
    }
}
