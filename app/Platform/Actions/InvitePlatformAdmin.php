<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use App\Platform\Audit;
use App\Platform\Mail\PlatformAdminInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * A6: a super-admin invites another admin. The raw token (32 random bytes) only travels in
 * the e-mail; the database keeps its sha256. One use, valid 72 hours.
 */
class InvitePlatformAdmin
{
    public function handle(PlatformAdmin $by, string $email, PlatformAdminRole $role): PlatformAdminInvitation
    {
        $email = strtolower($email);
        $token = bin2hex(random_bytes(32));

        $invitation = DB::transaction(function () use ($by, $email, $role, $token): PlatformAdminInvitation {
            // A new invitation replaces any pending one for the same address.
            PlatformAdminInvitation::query()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return PlatformAdminInvitation::query()->create([
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'invited_by_admin_id' => $by->getKey(),
                'expires_at' => now()->addHours((int) config('platform.invitation_ttl_hours')),
            ]);
        });

        Mail::to($email)->send(new PlatformAdminInvitationMail($invitation, route('admin.invitations.accept', $token), $by));
        Audit::record($by, 'admin.invited', $invitation, ['email' => $email, 'role' => $role->value]);

        return $invitation;
    }
}
