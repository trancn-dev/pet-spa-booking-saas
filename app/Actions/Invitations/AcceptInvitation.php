<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Actions\Staff\StaffQuota;
use App\Enums\InvitationState;
use App\Enums\StaffStatus;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * S13 / db-schema 2.2: the invited user joins the shop. One transaction on the locked invitation
 * row: staff (+ roles, staff_branches), accepted_at. Idempotent: accepting again as the same user
 * returns the same staff row. Roles or branches deleted since the invitation are skipped and
 * logged for the inviter. The owner works at every branch. Checks `plans.max_staff` (task 4.4).
 */
class AcceptInvitation
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PermissionRegistrar $permissions,
        private readonly StaffQuota $quota,
    ) {}

    public function handle(Tenant $tenant, Invitation $found, User $user): Staff
    {
        return $this->context->runAs($tenant, fn (): Staff => DB::transaction(function () use ($tenant, $found, $user): Staff {
            // Lock order everywhere: tenant row (staff quota) → invitation row.
            $this->quota->lockTenant($tenant);
            $invitation = Invitation::query()->lockForUpdate()->findOrFail($found->getKey());

            if (strtolower($user->email) !== $invitation->email) {
                throw ValidationException::withMessages([
                    'email' => 'Lời mời này dành cho '.$invitation->email.'. Hãy đăng nhập đúng email đó.',
                ]);
            }

            $existing = Staff::query()->where('user_id', $user->getKey())->first();

            if ($invitation->state() === InvitationState::Accepted && $existing !== null) {
                return $existing; // double submit / second click
            }

            if ($tenant->status->isReadOnly()) {
                throw ValidationException::withMessages(['invitation' => 'Shop đang tạm khóa, chưa nhận lời mời được. Liên hệ người mời.']);
            }

            if ($invitation->state() !== InvitationState::Pending) {
                throw ValidationException::withMessages(['invitation' => self::message($invitation->state())]);
            }

            // A new active account takes the seat this invitation was holding (plans.max_staff).
            if ($existing?->status !== StaffStatus::Active) {
                $this->quota->ensureRoomForOne($tenant, $invitation, 'invitation');
            }

            [$primaryId, $branchIds, $skippedBranches] = $this->branches($invitation);
            [$roles, $skippedRoles] = $this->roles($tenant, $invitation);
            $isOwner = in_array('owner', $roles, true);

            $staff = $existing ?? Staff::query()->create([
                'user_id' => $user->getKey(),
                'primary_branch_id' => $primaryId,
                'display_name' => $user->name,
                'phone' => $isOwner ? $tenant->contact_phone : null,
                'title' => $isOwner ? 'Chủ shop' : null,
            ]);
            $staff->forceFill(['status' => StaffStatus::Active])->save();

            $pivot = [];
            foreach ($branchIds as $id) {
                $pivot[$id] = ['tenant_id' => $tenant->getKey(), 'is_primary' => $id === $staff->primary_branch_id];
            }
            $staff->branches()->syncWithoutDetaching($pivot);

            $previousTeam = $this->permissions->getPermissionsTeamId();
            $this->permissions->setPermissionsTeamId($tenant->getKey());
            try {
                $user->unsetRelation('roles')->assignRole($roles);
            } finally {
                $this->permissions->setPermissionsTeamId($previousTeam);
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }

            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->forceFill([
                'last_tenant_id' => $tenant->getKey(),
                // The token arrived by e-mail: the address is proven.
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            ActivityLog::record('invitation.accepted', $invitation, array_filter([
                'staff_id' => $staff->id,
                'roles' => $roles,
                'skipped_roles' => $skippedRoles,
                'skipped_branches' => $skippedBranches,
            ]));

            return $staff;
        }));
    }

    public static function message(InvitationState $state): string
    {
        return match ($state) {
            InvitationState::Expired => 'Link đã hết hạn, liên hệ người mời.',
            InvitationState::Revoked => 'Lời mời đã bị thu hồi, liên hệ người mời.',
            InvitationState::Accepted => 'Lời mời đã được sử dụng.',
            InvitationState::Pending => '',
        };
    }

    /**
     * @return array{0: int, 1: list<int>, 2: list<int>} primary, all branch ids, skipped ids
     */
    private function branches(Invitation $invitation): array
    {
        $active = Branch::query()->where('is_active', true)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $wanted = $invitation->invitesOwner()
            ? $active
            : array_values(array_unique(array_map('intval', [...array_filter([$invitation->primary_branch_id]), ...($invitation->branch_ids ?? [])])));

        $ids = array_values(array_intersect($wanted, $active));
        $skipped = array_values(array_diff($wanted, $active));

        if ($ids === []) {
            throw ValidationException::withMessages(['invitation' => 'Cơ sở trong lời mời không còn hoạt động, liên hệ người mời.']);
        }

        $primary = in_array((int) $invitation->primary_branch_id, $ids, true) ? (int) $invitation->primary_branch_id : $ids[0];

        return [$primary, $ids, $skipped];
    }

    /**
     * @return array{0: list<string>, 1: list<string>} roles to give, skipped codes
     */
    private function roles(Tenant $tenant, Invitation $invitation): array
    {
        $wanted = array_values(array_filter(
            $invitation->role_names,
            fn (string $name): bool => $name !== 'owner' || $invitation->invitesOwner(),
        ));

        $existing = Role::query()->where('team_id', $tenant->getKey())->whereIn('name', $wanted)->pluck('name')->all();

        return [array_values(array_intersect($wanted, $existing)), array_values(array_diff($invitation->role_names, $existing))];
    }
}
