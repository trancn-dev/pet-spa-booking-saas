<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ActivityLog as ActivityLogEntry;
use App\Platform\Impersonation;
use App\Tenancy\CurrentStaff;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the shop's append-only activity log (S32) for the current tenant and staff member.
 */
final class ActivityLog
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(string $action, ?Model $subject = null, array $changes = [], ?string $reason = null, ?int $branchId = null): ActivityLogEntry
    {
        return ActivityLogEntry::query()->create([
            'branch_id' => $branchId,
            'actor_staff_id' => app(CurrentStaff::class)->get()?->id,
            // S32 shows "Admin … thực hiện thay" for rows written while impersonating.
            'impersonation_session_id' => request()->hasSession() ? request()->session()->get(Impersonation::SESSION_KEY) : null,
            'action' => $action,
            'subject_type' => $subject?->getTable(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
            'reason' => $reason,
            'ip' => request()->ip(),
        ]);
    }
}
