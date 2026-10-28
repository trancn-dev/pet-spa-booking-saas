<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Platform\PlatformAdmin;
use App\Models\Staff;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Creates a shop invitation in the current tenant (db-schema 2.2). The raw token (32 random bytes)
 * is returned once and only travels in the e-mail / copied link; the database keeps its sha256.
 * Exactly one inviter: a platform admin (owner invitations) or a staff member.
 */
class IssueInvitation
{
    /**
     * @param  list<string>  $roleNames
     * @param  list<int>  $branchIds  secondary branches
     * @return array{0: Invitation, 1: string}
     */
    public function handle(string $email, array $roleNames, ?int $primaryBranchId, array $branchIds = [], ?PlatformAdmin $admin = null, ?Staff $staff = null): array
    {
        if (($admin === null) === ($staff === null)) {
            throw new InvalidArgumentException('An invitation has exactly one inviter.');
        }

        $token = self::newToken();

        $invitation = Invitation::query()->create([
            'email' => strtolower(trim($email)),
            'role_names' => $roleNames,
            'primary_branch_id' => $primaryBranchId,
            'branch_ids' => $branchIds,
            'token_hash' => hash('sha256', $token),
            'invited_by_admin_id' => $admin?->getKey(),
            'invited_by_staff_id' => $staff?->getKey(),
            'expires_at' => self::expiresAt(),
        ]);

        return [$invitation, $token];
    }

    /**
     * "Gửi lại": a new token and a new expiry on the same row; the old link stops working.
     */
    public function rotate(Invitation $invitation): string
    {
        $token = self::newToken();
        $invitation->forceFill(['token_hash' => hash('sha256', $token), 'expires_at' => self::expiresAt()])->save();

        return $token;
    }

    private static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function expiresAt(): Carbon
    {
        return now()->addDays((int) config('platform.shop_invitation_ttl_days'));
    }
}
