<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Platform\Impersonation;
use App\Tenancy\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * CMS side of "Đăng nhập như chủ shop" (task 4.6). Outside the `tenant` middleware on purpose:
 * whoever is signed in to the CMS in this browser is signed out first.
 */
class ImpersonationController extends Controller
{
    public function consume(Request $request, string $slug, string $token, TenantMembership $membership): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        abort_if($tenant === null || ! $tenant->status->isAccessible(), 404);

        $session = Impersonation::consume($tenant, $token);
        $owner = $session === null ? null : User::query()->find($session->target_user_id);
        abort_if($session === null || $owner === null || ! $membership->isMember($owner, $tenant), 404);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();
        $request->session()->put(Impersonation::SESSION_KEY, $session->getKey());

        return redirect()->route('app.dashboard', ['tenant' => $tenant->slug])
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Banner "Thoát": ends the session row and signs out of the CMS.
     */
    public function exit(Request $request): RedirectResponse
    {
        $session = Impersonation::current($request);
        if ($session !== null) {
            Impersonation::end($session, 'exit');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Đã thoát phiên hỗ trợ.');
    }
}
