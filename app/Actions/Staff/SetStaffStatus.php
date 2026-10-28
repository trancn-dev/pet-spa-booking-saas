<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\StaffStatus;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S11 "Vô hiệu" / "Kích hoạt lại" (never delete: history points at staff rows). A disabled person
 * loses this shop at their next request (ResolveTenant). Re-enabling an account holder takes a
 * `max_staff` seat again.
 */
class SetStaffStatus
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly StaffQuota $quota,
    ) {}

    public function handle(Staff $by, Staff $staff, StaffStatus $status): void
    {
        DB::transaction(function () use ($by, $staff, $status): void {
            $tenant = $this->quota->lockTenant($this->context->require());
            $staff = Staff::query()->lockForUpdate()->findOrFail($staff->getKey());

            if ($staff->status === $status) {
                return;
            }

            if ($status === StaffStatus::Disabled) {
                if ($staff->is($by)) {
                    throw ValidationException::withMessages(['status' => 'Không tự vô hiệu chính mình được.']);
                }
                $user = $staff->user_id === null ? null : User::query()->find($staff->user_id);
                if ($user?->hasRole(Role::OWNER)) {
                    throw ValidationException::withMessages(['status' => 'Không vô hiệu được Chủ shop.']);
                }
            } elseif ($staff->user_id !== null) {
                $this->quota->ensureRoomForOne($tenant, field: 'status');
            }

            $staff->forceFill(['status' => $status])->save();
            ActivityLog::record($status === StaffStatus::Disabled ? 'staff.disabled' : 'staff.enabled', $staff);
        });
    }
}
