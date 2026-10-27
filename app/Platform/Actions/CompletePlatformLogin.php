<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Models\Platform\PlatformAdmin;
use App\Platform\Audit;
use App\Platform\PendingLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signs the admin into the platform guard once every factor has been checked.
 */
class CompletePlatformLogin
{
    public function handle(Request $request, PlatformAdmin $admin): RedirectResponse
    {
        PendingLogin::forget($request);

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        Audit::record($admin, 'admin.login', $admin);

        return redirect()->intended(route('admin.tenants.index'));
    }
}
