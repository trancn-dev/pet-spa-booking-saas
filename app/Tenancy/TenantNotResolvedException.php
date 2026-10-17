<?php

declare(strict_types=1);

namespace App\Tenancy;

use RuntimeException;

class TenantNotResolvedException extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("No current tenant: refusing to query or write tenant-owned model [{$model}]. "
            .'Resolve a tenant first (ResolveTenant middleware / TenantContext::run) or use TenantContext::withoutTenancy() explicitly.');
    }
}
