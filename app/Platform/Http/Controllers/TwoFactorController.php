<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Actions\CompletePlatformLogin;
use App\Platform\PendingLogin;
use App\Platform\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A1 step 2 (6-digit TOTP) and A1.1 (first-time 2FA setup). 2FA is mandatory for every admin.
 */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function challenge(Request $request): Response|RedirectResponse
    {
        $admin = PendingLogin::get($request);

        if ($admin === null) {
            return to_route('admin.login');
        }

        if (! $admin->hasTwoFactor()) {
            return to_route('admin.two-factor.setup');
        }

        return Inertia::render('Admin/TwoFactor/Challenge', ['email' => $admin->email]);
    }

    public function verify(Request $request, CompletePlatformLogin $complete): RedirectResponse
    {
        $admin = PendingLogin::get($request);

        if ($admin === null || ! $admin->hasTwoFactor()) {
            return to_route('admin.login');
        }

        $this->checkCode($request, $admin->getKey(), fn (string $code): bool => $this->twoFactor->verify($admin, (string) $admin->two_factor_secret, $code));

        return $complete->handle($request, $admin);
    }

    public function setup(Request $request): Response|RedirectResponse
    {
        $admin = PendingLogin::get($request);

        if ($admin === null) {
            return to_route('admin.login');
        }

        if ($admin->hasTwoFactor()) {
            return to_route('admin.two-factor.challenge');
        }

        // Keep the same secret until it is confirmed, so a reload does not invalidate a scanned QR.
        if ($admin->two_factor_secret === null) {
            $admin->forceFill(['two_factor_secret' => $this->twoFactor->generateSecret()])->save();
        }

        $secret = (string) $admin->two_factor_secret;

        return Inertia::render('Admin/TwoFactor/Setup', [
            'email' => $admin->email,
            'qrSvg' => $this->twoFactor->qrCodeSvg($admin, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirm(Request $request, CompletePlatformLogin $complete): RedirectResponse
    {
        $admin = PendingLogin::get($request);

        if ($admin === null || $admin->two_factor_secret === null) {
            return to_route('admin.login');
        }

        $this->checkCode($request, $admin->getKey(), fn (string $code): bool => $this->twoFactor->verify($admin, (string) $admin->two_factor_secret, $code));

        $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

        return $complete->handle($request, $admin);
    }

    /**
     * @param  callable(string): bool  $verify
     */
    private function checkCode(Request $request, mixed $adminId, callable $verify): void
    {
        $code = (string) $request->validate(['code' => ['required', 'string']])['code'];
        $key = 'platform-2fa:'.(is_scalar($adminId) ? $adminId : '');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Quá nhiều lần thử. Thử lại sau '.RateLimiter::availableIn($key).' giây.',
            ]);
        }

        if (! $verify($code)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => 'Mã xác thực không đúng hoặc đã dùng.']);
        }

        RateLimiter::clear($key);
    }
}
