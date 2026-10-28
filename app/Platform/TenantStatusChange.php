<?php

declare(strict_types=1);

namespace App\Platform;

use App\Enums\TenantStatus;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;

/**
 * Status changes are compare-and-set (`UPDATE … WHERE status = :expected`, db-schema 2.1): the
 * daily expiry job and an admin acting at the same moment can never overwrite each other. Exactly
 * one platform_audit_logs row per change that actually happened.
 */
final class TenantStatusChange
{
    /** suspended_reason written by the expiry job; an extension lifts only this kind of suspension. */
    public const EXPIRED_REASON = 'Hết hạn sử dụng';

    /**
     * @param  array<string, mixed>  $attributes  other columns to set with the status
     * @param  array<string, mixed>  $changes  extra audit details
     * @return bool false when the status was no longer `$expected` (nothing written)
     */
    public static function apply(Tenant $tenant, TenantStatus $expected, TenantStatus $to, string $action, ?PlatformAdmin $by = null, array $attributes = [], array $changes = []): bool
    {
        $updated = Tenant::query()
            ->whereKey($tenant->getKey())
            ->where('status', $expected->value)
            ->update(['status' => $to->value, ...$attributes, 'updated_at' => now()]);

        if ($updated === 0) {
            return false;
        }

        $tenant->refresh();
        Audit::record($by, $action, $tenant, ['status' => [$expected->value, $to->value], ...$changes], $tenant->getKey());

        return true;
    }
}
