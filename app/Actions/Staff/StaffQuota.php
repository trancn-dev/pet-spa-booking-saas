<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Enums\StaffStatus;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * `plans.max_staff` (db-schema 2.1): active staff WITH an account (owner included) plus invitations
 * still pending. People without an account are free. Checked when inviting, accepting and
 * re-enabling, always inside a transaction after `lockTenant()`, so two of them at once cannot
 * both pass.
 */
class StaffQuota
{
    public const FULL = 'Shop đã đủ số nhân viên của gói. Liên hệ PawBook để nâng gói.';

    /**
     * Locks the shop row: every quota check of this shop queues behind it.
     */
    public function lockTenant(Tenant $tenant): Tenant
    {
        return Tenant::query()->lockForUpdate()->findOrFail($tenant->getKey());
    }

    /**
     * @return array{used: int, max: int|null}
     */
    public function usage(Tenant $tenant, ?Invitation $except = null): array
    {
        $staff = Staff::query()->whereNotNull('user_id')->where('status', StaffStatus::Active)->count();
        $pending = Invitation::query()
            ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except?->getKey()))
            ->count();

        $max = Plan::query()->whereKey($tenant->plan_id)->value('max_staff');

        return ['used' => $staff + $pending, 'max' => $max === null ? null : (int) $max];
    }

    /**
     * One more seat must fit. `$except`: the invitation being accepted (already counted as pending).
     *
     * @throws ValidationException
     */
    public function ensureRoomForOne(Tenant $tenant, ?Invitation $except = null, string $field = 'email'): void
    {
        ['used' => $used, 'max' => $max] = $this->usage($tenant, $except);

        if ($max !== null && $used + 1 > $max) {
            throw ValidationException::withMessages([$field => self::FULL]);
        }
    }
}
