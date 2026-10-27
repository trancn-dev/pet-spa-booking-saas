<?php

declare(strict_types=1);

use App\Platform\Http\Controllers\AdminController;
use App\Platform\Http\Controllers\AuthController;
use App\Platform\Http\Controllers\InvitationController;
use App\Platform\Http\Controllers\TenantController;
use App\Platform\Http\Controllers\TwoFactorController;
use App\Platform\Http\Middleware\RedirectIfPlatformAdmin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
| Platform admin area, mounted at /admin with the `platform` middleware group (own session
| cookie, no tenant middleware) and route names prefixed `admin.` (see bootstrap/app.php).
*/

Route::get('/', fn () => Auth::guard('platform')->check()
    ? to_route('admin.tenants.index')
    : to_route('admin.login'))->name('home');

Route::middleware(RedirectIfPlatformAdmin::class)->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->name('login.store');

    Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::get('two-factor/setup', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');

    Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.accept');
    Route::post('invite/{token}', [InvitationController::class, 'accept'])->name('invitations.store');
});

Route::middleware('platform.auth')->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');

    Route::middleware('platform.role:super_admin')->group(function () {
        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        Route::post('admins/invitations', [AdminController::class, 'invite'])->name('admins.invite');
        Route::delete('admins/invitations/{invitation}', [AdminController::class, 'revoke'])->name('admins.invitations.revoke');
        Route::patch('admins/{admin}/status', [AdminController::class, 'setStatus'])->name('admins.status');
    });
});
