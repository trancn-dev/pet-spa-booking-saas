<?php

declare(strict_types=1);

namespace App\Platform;

use App\Models\Platform\PlatformAdmin;
use Illuminate\Http\Request;

/**
 * An admin who passed the password step (or accepted an invitation) but not yet the TOTP step.
 * Not signed in: the platform guard is only logged in once the second factor is verified.
 */
final class PendingLogin
{
    private const KEY = 'platform.pending_login';

    private const TTL_SECONDS = 600;

    public static function put(Request $request, PlatformAdmin $admin): void
    {
        $request->session()->put(self::KEY, ['id' => $admin->getKey(), 'expires' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp()]);
    }

    public static function get(Request $request): ?PlatformAdmin
    {
        $pending = $request->session()->get(self::KEY);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $admin = PlatformAdmin::query()->find($pending['id'] ?? null);

        return $admin !== null && $admin->is_active ? $admin : null;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
