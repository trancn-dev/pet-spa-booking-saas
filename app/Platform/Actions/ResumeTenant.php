<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\TenantStatusChange;
use Illuminate\Validation\ValidationException;

/**
 * A4 "Mở khóa": back to active while the plan is still valid. An expired shop is reopened by
 * extending it (ExtendSubscription), not here.
 */
class ResumeTenant
{
    public function handle(PlatformAdmin $by, Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::Suspended) {
            throw ValidationException::withMessages(['status' => 'Shop không bị tạm khóa.']);
        }

        if ($tenant->plan_expires_at === null || $tenant->plan_expires_at->isPast()) {
            throw ValidationException::withMessages(['status' => 'Gói đã hết hạn: gia hạn để mở lại shop.']);
        }

        $applied = TenantStatusChange::apply($tenant, TenantStatus::Suspended, TenantStatus::Active, 'tenant.resumed', $by, [
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);

        if (! $applied) {
            throw ValidationException::withMessages(['status' => 'Trạng thái shop vừa thay đổi, tải lại trang rồi thử lại.']);
        }
    }
}
