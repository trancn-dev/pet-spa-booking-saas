<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use App\Platform\Audit;
use Illuminate\Support\Facades\DB;

/**
 * A7: the invited person sets a name and password. The invitation row is locked so the same
 * token can never create two admins; 2FA setup is mandatory right after.
 */
class AcceptPlatformAdminInvitation
{
    public function __construct(private readonly CreatePlatformAdmin $createAdmin) {}

    /**
     * Pending (not accepted, not revoked, not expired) invitation for a raw token, or null.
     */
    public static function find(string $token, bool $lock = false): ?PlatformAdminInvitation
    {
        return PlatformAdminInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function handle(string $token, string $name, string $password): ?PlatformAdmin
    {
        return DB::transaction(function () use ($token, $name, $password): ?PlatformAdmin {
            $invitation = self::find($token, lock: true);

            if ($invitation === null) {
                return null;
            }

            $admin = $this->createAdmin->handle($name, $invitation->email, $password, $invitation->role);
            $invitation->forceFill(['accepted_at' => now()])->save();
            Audit::record($admin, 'admin.invitation_accepted', $invitation);

            return $admin;
        });
    }
}
