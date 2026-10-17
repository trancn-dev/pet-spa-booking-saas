<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant for the current request / job. Registered as a scoped singleton.
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

    public function id(): int|string|null
    {
        $key = $this->tenant?->getKey();

        return is_int($key) || is_string($key) ? $key : null;
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
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }

    /**
     * Explicit, greppable escape hatch for cross-tenant work (admin, migrations, reports).
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
}
