<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * `exists` / `unique` rules query the table directly and bypass global scopes:
 * on tenant-owned tables always build them through here.
 *
 *   'pet_id' => ['required', TenantRule::exists('pets')],
 *   'phone'  => [TenantRule::unique('customers', 'phone')->ignore($customer)],
 */
final class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return (new Exists($table, $column))->where('tenant_id', self::tenantId());
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return (new Unique($table, $column))->where('tenant_id', self::tenantId());
    }

    private static function tenantId(): int
    {
        return (int) app(TenantContext::class)->require()->getKey();
    }
}
