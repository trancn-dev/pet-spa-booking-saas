<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Invitations\IssueInvitation;
use App\Enums\StaffStatus;
use App\Mail\ShopInvitationMail;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * S11 "Mời nhân viên" (task 4.4): e-mail + one or more roles (never `owner`) + primary and extra
 * branches, within `plans.max_staff`. A new invitation to the same address replaces the pending one.
 * The mail is queued after the commit and carries plain values only.
 */
class InviteStaff
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly IssueInvitation $invitations,
        private readonly StaffQuota $quota,
    ) {}

    /**
     * @param  list<string>  $roleNames
     * @param  list<int>  $branchIds  extra branches
     */
    public function handle(Staff $by, string $email, array $roleNames, int $primaryBranchId, array $branchIds = []): Invitation
    {
        $email = strtolower(trim($email));
        $roleNames = array_values(array_unique($roleNames));
        $branchIds = array_values(array_diff(array_unique($branchIds), [$primaryBranchId]));

        $this->validate($email, $roleNames, $primaryBranchId, $branchIds);

        [$invitation, $token] = DB::transaction(function () use ($by, $email, $roleNames, $primaryBranchId, $branchIds): array {
            $tenant = $this->quota->lockTenant($this->context->require());

            Invitation::query()->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->quota->ensureRoomForOne($tenant);

            $issued = $this->invitations->handle($email, $roleNames, $primaryBranchId, $branchIds, staff: $by);
            ActivityLog::record('invitation.created', $issued[0], ['email' => $email, 'roles' => $roleNames]);

            return $issued;
        });

        self::mail($invitation, $token, $by);

        return $invitation;
    }

    public static function mail(Invitation $invitation, string $token, Staff $by): void
    {
        $tenant = app(TenantContext::class)->require();
        $roles = Role::query()->where('team_id', $tenant->getKey())->whereIn('name', $invitation->role_names)
            ->orderBy('id')->pluck('display_name')->implode(', ');

        Mail::to($invitation->email)->queue(new ShopInvitationMail(
            shopName: $tenant->name,
            roleLabel: $roles !== '' ? $roles : 'nhân viên',
            url: route('invitations.show', $token),
            expiresAt: $invitation->expires_at?->timezone($tenant->timezone)->format('H:i d/m/Y') ?? '',
            invitedBy: $by->display_name,
        ));
    }

    /**
     * @param  list<string>  $roleNames
     * @param  list<int>  $branchIds
     */
    private function validate(string $email, array $roleNames, int $primaryBranchId, array $branchIds): void
    {
        $errors = [];

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email không hợp lệ.';
        } else {
            $userId = User::query()->where('email', $email)->value('id');
            if ($userId !== null && Staff::query()->where('user_id', $userId)->where('status', StaffStatus::Active)->exists()) {
                $errors['email'] = 'Người này đã là nhân viên của shop.';
            }
        }

        $tenantId = $this->context->require()->getKey();
        $known = Role::query()->where('team_id', $tenantId)->whereIn('name', $roleNames)->pluck('name')->all();
        if ($roleNames === []) {
            $errors['roles'] = 'Chọn ít nhất một role.';
        } elseif (in_array(Role::OWNER, $roleNames, true)) {
            $errors['roles'] = 'Không mời được role Chủ shop.';
        } elseif (count($known) !== count($roleNames)) {
            $errors['roles'] = 'Role không tồn tại.';
        }

        $active = Branch::query()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (! in_array($primaryBranchId, $active, true)) {
            $errors['primary_branch'] = 'Chọn cơ sở chính đang hoạt động.';
        } elseif (array_diff($branchIds, $active) !== []) {
            $errors['branches'] = 'Cơ sở phụ không hợp lệ.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
