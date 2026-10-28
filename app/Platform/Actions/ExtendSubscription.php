<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\TenantSubscription;
use App\Models\Tenant;
use App\Platform\TenantStatusChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A4 "Gia hạn" (F20): a new tenant_subscriptions row that continues from the current expiry (or
 * from now when already expired), and the tenants summary (plan, expiry, status) in the same
 * transaction. Trial, past due and expiry-suspended shops become active at once; a shop suspended
 * by an admin for another reason stays suspended until "Mở khóa".
 */
class ExtendSubscription
{
    public function handle(PlatformAdmin $by, Tenant $tenant, Plan $plan, int $months, string $amount, ?string $paymentRef = null, ?string $note = null): TenantSubscription
    {
        if ($months < 1 || $months > 36) {
            throw ValidationException::withMessages(['months' => 'Số tháng từ 1 đến 36.']);
        }

        return DB::transaction(function () use ($by, $tenant, $plan, $months, $amount, $paymentRef, $note): TenantSubscription {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());

            if ($tenant->status === TenantStatus::Cancelled) {
                throw ValidationException::withMessages(['months' => 'Shop đã hủy.']);
            }

            $current = $tenant->plan_expires_at;
            $starts = $current !== null && $current->isFuture() ? $current->copy() : now();
            $ends = $starts->copy()->addMonthsNoOverflow($months);

            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'starts_at' => $starts,
                'ends_at' => $ends,
                'amount' => $amount,
                'paid_at' => now(),
                'payment_ref' => $paymentRef,
                'note' => $note,
                'created_by_admin_id' => $by->getKey(),
            ]);

            $reopen = match ($tenant->status) {
                TenantStatus::Suspended => $tenant->suspended_reason === TenantStatusChange::EXPIRED_REASON,
                default => true,
            };
            $to = $reopen ? TenantStatus::Active : $tenant->status;

            $applied = TenantStatusChange::apply($tenant, $tenant->status, $to, 'tenant.extended', $by, [
                'plan_id' => $plan->getKey(),
                'plan_expires_at' => $ends,
                ...($reopen ? ['suspended_at' => null, 'suspended_reason' => null] : []),
            ], [
                'plan' => $plan->code,
                'months' => $months,
                'from' => $starts->toIso8601String(),
                'until' => $ends->toIso8601String(),
                'amount' => $amount,
                'payment_ref' => $paymentRef,
            ]);

            if (! $applied) {
                throw ValidationException::withMessages(['months' => 'Trạng thái shop vừa thay đổi, tải lại trang rồi thử lại.']);
            }

            return $subscription;
        });
    }
}
