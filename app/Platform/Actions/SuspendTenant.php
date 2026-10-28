<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\TenantStatusChange;
use Illuminate\Validation\ValidationException;

/**
 * A4 modal "Tạm khóa" (reason required): staff can still look but not act, the Site stops taking
 * bookings. Compare-and-set against the status the admin saw.
 */
class SuspendTenant
{
    public function handle(PlatformAdmin $by, Tenant $tenant, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Nhập lý do tạm khóa.']);
        }

        if (in_array($tenant->status, [TenantStatus::Suspended, TenantStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['reason' => 'Shop đang '.mb_strtolower($tenant->status->label()).'.']);
        }

        $applied = TenantStatusChange::apply($tenant, $tenant->status, TenantStatus::Suspended, 'tenant.suspended', $by, [
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ], ['reason' => $reason]);

        if (! $applied) {
            throw ValidationException::withMessages(['reason' => 'Trạng thái shop vừa thay đổi, tải lại trang rồi thử lại.']);
        }
    }
}
