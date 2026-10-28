<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Enums\PlatformAdminRole;
use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use App\Platform\Actions\InvitePlatformAdmin;
use App\Platform\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A6: admins and pending invitations (super-admin only). Admins are disabled, never deleted.
 */
class AdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Admins/Index', [
            'admins' => PlatformAdmin::query()->orderBy('name')->get()->map(fn (PlatformAdmin $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'two_factor' => $admin->hasTwoFactor(),
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
                'is_active' => $admin->is_active,
            ]),
            'invitations' => PlatformAdminInvitation::query()
                ->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->orderByDesc('id')->get()
                ->map(fn (PlatformAdminInvitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ]),
        ]);
    }

    public function invite(Request $request, InvitePlatformAdmin $invite): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191', Rule::unique('platform_admins', 'email')],
            'role' => ['required', Rule::enum(PlatformAdminRole::class)],
        ], ['email.unique' => 'Email này đã là quản trị viên.']);

        $invite->handle($this->admin($request), $data['email'], PlatformAdminRole::from($data['role']));

        return back()->with('status', 'Đã gửi lời mời tới '.$data['email'].'.');
    }

    public function revoke(Request $request, PlatformAdminInvitation $invitation): RedirectResponse
    {
        if ($invitation->accepted_at === null && $invitation->revoked_at === null) {
            $invitation->forceFill(['revoked_at' => now()])->save();
            Audit::record($this->admin($request), 'admin.invitation_revoked', $invitation, ['email' => $invitation->email]);
        }

        return back()->with('status', 'Đã thu hồi lời mời.');
    }

    public function setStatus(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        $active = $request->validate(['is_active' => ['required', 'boolean']])['is_active'];
        $me = $this->admin($request);

        if ($admin->is($me)) {
            throw ValidationException::withMessages(['is_active' => 'Không thể tự vô hiệu hóa chính mình.']);
        }

        $admin->forceFill(['is_active' => (bool) $active])->save();
        Audit::record($me, $active ? 'admin.enabled' : 'admin.disabled', $admin, ['email' => $admin->email]);

        return back()->with('status', $active ? 'Đã kích hoạt lại.' : 'Đã vô hiệu hóa.');
    }

    private function admin(Request $request): PlatformAdmin
    {
        $admin = $request->user('platform');
        assert($admin instanceof PlatformAdmin);

        return $admin;
    }
}
