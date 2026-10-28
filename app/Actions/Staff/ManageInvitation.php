<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Actions\Invitations\IssueInvitation;
use App\Enums\InvitationState;
use App\Models\Invitation;
use App\Models\Staff;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S11 tab "Lời mời": resend (new token, the old link dies, new 7 days) or revoke a staff invitation.
 * The owner's invitation is the platform admin's (A4), not the shop's.
 */
class ManageInvitation
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly IssueInvitation $invitations,
        private readonly StaffQuota $quota,
    ) {}

    public function resend(Staff $by, Invitation $invitation): void
    {
        $token = DB::transaction(function () use ($invitation): string {
            $tenant = $this->quota->lockTenant($this->context->require());
            $invitation = $this->lockOpen($invitation);

            // An expired invitation no longer holds a seat: resending takes one again.
            if ($invitation->state() === InvitationState::Expired) {
                $this->quota->ensureRoomForOne($tenant, $invitation, 'invitation');
            }

            $token = $this->invitations->rotate($invitation);
            ActivityLog::record('invitation.resent', $invitation, ['email' => $invitation->email]);

            return $token;
        });

        InviteStaff::mail($invitation->refresh(), $token, $by);
    }

    public function revoke(Invitation $invitation): void
    {
        DB::transaction(function () use ($invitation): void {
            $invitation = $this->lockOpen($invitation);
            $invitation->forceFill(['revoked_at' => now()])->save();
            ActivityLog::record('invitation.revoked', $invitation, ['email' => $invitation->email]);
        });
    }

    private function lockOpen(Invitation $invitation): Invitation
    {
        $locked = Invitation::query()->lockForUpdate()->findOrFail($invitation->getKey());

        if ($locked->invited_by_admin_id !== null) {
            throw ValidationException::withMessages(['invitation' => 'Lời mời chủ shop do PawBook quản lý.']);
        }

        if (in_array($locked->state(), [InvitationState::Accepted, InvitationState::Revoked], true)) {
            throw ValidationException::withMessages(['invitation' => 'Lời mời đã '.($locked->state() === InvitationState::Accepted ? 'được nhận' : 'bị thu hồi').'.']);
        }

        return $locked;
    }
}
