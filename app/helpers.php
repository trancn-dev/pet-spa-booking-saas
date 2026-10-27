<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    /**
     * The current tenant, or null outside a tenant context.
     */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->get();
    }
}

if (! function_exists('tenant_cache_key')) {
    /**
     * `t:{tenant_id}:{key}` for cache, lock and rate-limiter keys. Throws outside a tenant context.
     */
    function tenant_cache_key(string $key): string
    {
        return app(TenantContext::class)->key($key);
    }
}
