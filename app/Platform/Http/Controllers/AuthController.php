<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAdmin;
use App\Platform\Actions\CompletePlatformLogin;
use App\Platform\Audit;
use App\Platform\PendingLogin;
use App\Platform\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A1 step 1: email + password. The guard is only signed in after the TOTP step.
 */
class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Login');
    }

    public function store(Request $request, CompletePlatformLogin $complete): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'platform-login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Quá nhiều lần thử. Thử lại sau '.RateLimiter::availableIn($key).' giây.',
            ]);
        }

        $admin = PlatformAdmin::query()->where('email', Str::lower($data['email']))->first();

        if ($admin === null || ! $admin->is_active || ! Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($key, 60);

            // One generic message: never reveal whether the email exists or is disabled.
            throw ValidationException::withMessages(['email' => 'Email hoặc mật khẩu không đúng.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if (! $admin->hasTwoFactor() && TwoFactor::canSkip()) {
            return $complete->handle($request, $admin);
        }

        PendingLogin::put($request, $admin);

        return $admin->hasTwoFactor()
            ? to_route('admin.two-factor.challenge')
            : to_route('admin.two-factor.setup');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();
        Audit::record($admin instanceof PlatformAdmin ? $admin : null, 'admin.logout');

        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }
}
