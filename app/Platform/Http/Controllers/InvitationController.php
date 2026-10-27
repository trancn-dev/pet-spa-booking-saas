<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Actions\AcceptPlatformAdminInvitation;
use App\Platform\PendingLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A7: accept an admin invitation → set password → mandatory 2FA setup.
 * Used, revoked, expired or unknown tokens are a 404.
 */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = AcceptPlatformAdminInvitation::find($token);
        abort_if($invitation === null, 404);

        return Inertia::render('admin/auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);
    }

    public function accept(Request $request, string $token, AcceptPlatformAdminInvitation $accept): RedirectResponse
    {
        abort_if(AcceptPlatformAdminInvitation::find($token) === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $admin = $accept->handle($token, $data['name'], $data['password']);
        abort_if($admin === null, 404);

        $request->session()->regenerate();
        PendingLogin::put($request, $admin);

        return to_route('admin.two-factor.setup');
    }
}
