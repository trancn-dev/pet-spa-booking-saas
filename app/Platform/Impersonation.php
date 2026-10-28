<?php

declare(strict_types=1);

namespace App\Platform;

use App\Enums\StaffStatus;
use App\Models\Platform\ImpersonationSession;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Đăng nhập như chủ shop" (ADR 0008, db-schema 2.1 / 5.7, F21). The admin session and the CMS
 * session use different cookies, so the handover is a one-time token: 32 random bytes, sha256 in
 * the DB, valid 60 seconds, consumed by a conditional UPDATE. The CMS session then lasts at most
 * 30 minutes and remembers its impersonation_sessions row.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonation_session_id';

    public const TOKEN_SECONDS = 60;

    public const SESSION_MINUTES = 30;

    /** Shown in the banner: what the guard refuses (ImpersonationGuard::BLOCKED). */
    public const BLOCKED_HINT = 'Không đổi mật khẩu/email, phân quyền, nhân viên, lương, sửa/hủy hóa đơn hay xóa dữ liệu trong phiên này.';

    /**
     * @return array{0: ImpersonationSession, 1: string} the session row and the raw token
     */
    public static function start(PlatformAdmin $by, Tenant $tenant, string $reason, Request $request): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Nhập lý do đăng nhập thay.']);
        }
        if (! $tenant->status->isAccessible()) {
            throw ValidationException::withMessages(['reason' => 'Shop đã hủy.']);
        }

        $owner = self::ownerOf($tenant);
        if ($owner === null) {
            throw ValidationException::withMessages(['reason' => 'Shop chưa có chủ shop đang hoạt động (chủ shop chưa nhận lời mời?).']);
        }

        $token = bin2hex(random_bytes(32));
        $session = ImpersonationSession::query()->create([
            'platform_admin_id' => $by->getKey(),
            'tenant_id' => $tenant->getKey(),
            'target_user_id' => $owner->getKey(),
            'reason' => $reason,
            'token_hash' => hash('sha256', $token),
            // Until consumed this is the token's deadline; then the session's (30 min).
            'expires_at' => now()->addSeconds(self::TOKEN_SECONDS),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        Audit::record($by, 'impersonation.started', $session, ['reason' => $reason, 'target_user_id' => $owner->getKey()], $tenant->getKey(), $session->getKey());

        return [$session, $token];
    }

    /**
     * Uses the token once, for this shop only. Null when unknown, used, expired or another shop's.
     */
    public static function consume(Tenant $tenant, string $token): ?ImpersonationSession
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $updated = DB::table('impersonation_sessions')
            ->where('token_hash', $hash)
            ->where('tenant_id', $tenant->getKey())
            ->whereNull('started_at')
            ->where('expires_at', '>', now())
            ->update(['started_at' => now(), 'expires_at' => now()->addMinutes(self::SESSION_MINUTES), 'updated_at' => now()]);

        return $updated === 0 ? null : ImpersonationSession::query()->where('token_hash', $hash)->first();
    }

    /**
     * The open impersonation of this CMS session, or null (none, ended or past 30 minutes).
     */
    public static function current(Request $request): ?ImpersonationSession
    {
        $id = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if ($id === null) {
            return null;
        }

        $session = ImpersonationSession::query()->with(['admin:id,name', 'tenant:id,slug,name'])->find($id);

        return $session !== null && $session->ended_at === null && $session->expires_at->isFuture() ? $session : null;
    }

    public static function end(ImpersonationSession $session, string $why = 'exit'): void
    {
        $ended = DB::table('impersonation_sessions')->where('id', $session->getKey())->whereNull('ended_at')
            ->update(['ended_at' => now(), 'updated_at' => now()]);

        if ($ended > 0) {
            Audit::record(null, 'impersonation.ended', $session, ['why' => $why], $session->tenant_id, $session->getKey());
        }
    }

    /**
     * The shop's owner to act as: an active staff member holding the `owner` role (oldest first).
     */
    public static function ownerOf(Tenant $tenant): ?User
    {
        $id = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('staff', fn ($join) => $join->on('staff.user_id', '=', 'model_has_roles.model_id')->on('staff.tenant_id', '=', 'model_has_roles.team_id'))
            ->where('model_has_roles.team_id', $tenant->getKey())
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', 'owner')
            ->where('staff.status', StaffStatus::Active->value)
            ->whereNull('staff.deleted_at')
            ->orderBy('staff.id')
            ->value('model_has_roles.model_id');

        return $id === null ? null : User::query()->find($id);
    }
}
