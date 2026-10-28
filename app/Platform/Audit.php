<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only trail of what platform admins did (A5).
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(?PlatformAdmin $admin, string $action, ?Model $subject = null, array $changes = [], ?int $tenantId = null, ?int $impersonationSessionId = null): void
    {
        PlatformAuditLog::query()->create([
            'platform_admin_id' => $admin?->getKey(),
            'impersonation_session_id' => $impersonationSessionId,
            'tenant_id' => $tenantId,
            'action' => $action,
            'subject_type' => $subject === null ? null : $subject->getTable(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'ip' => request()->ip(),
        ]);
    }
}
