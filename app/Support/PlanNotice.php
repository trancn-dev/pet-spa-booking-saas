<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TenantStatus;
use App\Models\Tenant;

/**
 * CMS banner about the plan (design 3.5 Banner, F20): amber when it ends within N days, red while
 * past due (with the days left before suspension) and while suspended.
 */
final class PlanNotice
{
    /**
     * @return array{level: 'warning'|'danger', text: string}|null
     */
    public static function for(Tenant $tenant): ?array
    {
        $expires = $tenant->plan_expires_at?->copy()->timezone($tenant->timezone);
        $date = $expires?->format('d/m/Y');

        return match (true) {
            $tenant->status === TenantStatus::Suspended => [
                'level' => 'danger',
                'text' => 'Shop đang tạm khóa: vẫn xem được dữ liệu nhưng không thao tác được, Site ngừng nhận lịch. Liên hệ PawBook để gia hạn.',
            ],
            $tenant->status === TenantStatus::PastDue && $expires !== null => [
                'level' => 'danger',
                'text' => sprintf(
                    'Gói đã hết hạn ngày %s. Shop sẽ bị tạm khóa sau %d ngày nếu chưa gia hạn.',
                    $date,
                    max(0, (int) ceil(now()->diffInDays($expires->copy()->addDays((int) config('platform.grace_days')), false))),
                ),
            ],
            in_array($tenant->status, [TenantStatus::Trial, TenantStatus::Active], true) && $expires !== null
                && $expires->isFuture() && $expires->lte(now()->addDays((int) config('platform.expiry_warning_days'))) => [
                    'level' => 'warning',
                    'text' => sprintf(
                        '%s hết hạn ngày %s (còn %d ngày). Liên hệ PawBook để gia hạn.',
                        $tenant->status === TenantStatus::Trial ? 'Bản dùng thử' : 'Gói',
                        $date,
                        max(1, (int) ceil(now()->diffInDays($expires, false))),
                    ),
                ],
            default => null,
        };
    }
}
