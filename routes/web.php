<?php

declare(strict_types=1);

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\SupportAccessController;
use App\Http\Controllers\WorkspaceRedirectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Central routes: users are global identities, so home, auth and account settings are not tenant-bound.
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Sends a logged-in user to their (last) workspace.
Route::get('dashboard', WorkspaceRedirectController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

// S13: accept a shop invitation (owner or staff). Guests and signed-in users alike.
Route::get('invite/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('invite/{token}', [InvitationController::class, 'accept'])
    ->middleware('throttle:10,1')->name('invitations.accept');

// Task 4.6: one-time token from /admin → CMS session as the shop owner (outside `tenant` on purpose).
Route::get('app/{slug}/impersonate/{token}', [ImpersonationController::class, 'consume'])
    ->middleware('throttle:20,1')->name('impersonation.consume');
Route::post('impersonation/exit', [ImpersonationController::class, 'exit'])->name('impersonation.exit');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Everything below belongs to one tenant, resolved from the slug; members only.
Route::middleware('tenant')->prefix('app/{tenant:slug}')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', fn () => to_route('app.dashboard'))->name('app.home');

        Route::get('dashboard', DashboardController::class)->name('app.dashboard');

        // Topbar branch switcher
        Route::post('branch/{branch}', [BranchController::class, 'switch'])->name('app.branch.switch');

        // S11 Nhân viên & lời mời, S11.1 hồ sơ
        Route::get('staff', [StaffController::class, 'index'])->name('app.staff.index');
        Route::get('staff/create', [StaffController::class, 'create'])->name('app.staff.create');
        Route::post('staff', [StaffController::class, 'store'])->name('app.staff.store');
        Route::post('staff/invitations', [StaffController::class, 'invite'])->name('app.staff.invite');
        Route::post('staff/invitations/{invitation}/resend', [StaffController::class, 'resendInvitation'])->name('app.staff.invitations.resend');
        Route::delete('staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])->name('app.staff.invitations.revoke');
        Route::get('staff/{staff}/edit', [StaffController::class, 'edit'])->name('app.staff.edit');
        Route::put('staff/{staff}', [StaffController::class, 'update'])->name('app.staff.update');
        Route::patch('staff/{staff}/status', [StaffController::class, 'setStatus'])->name('app.staff.status');

        // Cài đặt › Lịch sử truy cập hỗ trợ (task 4.6)
        Route::get('settings/support-access', SupportAccessController::class)->name('app.settings.support-access');

        // S12.2 Cài đặt › Cơ sở
        Route::get('settings/branches', [BranchController::class, 'index'])->name('app.settings.branches');
        Route::get('settings/branches/create', [BranchController::class, 'create'])->name('app.settings.branches.create');
        Route::post('settings/branches', [BranchController::class, 'store'])->name('app.settings.branches.store');
        Route::get('settings/branches/{branch}', [BranchController::class, 'edit'])->name('app.settings.branches.edit');
        Route::put('settings/branches/{branch}', [BranchController::class, 'update'])->name('app.settings.branches.update');
        Route::delete('settings/branches/{branch}', [BranchController::class, 'destroy'])->name('app.settings.branches.destroy');

        // S12.3 Cài đặt › Phân quyền
        Route::get('settings/roles', [RoleController::class, 'index'])->name('app.settings.roles');
        Route::post('settings/roles', [RoleController::class, 'store'])->name('app.settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('app.settings.roles.update');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('app.settings.roles.destroy');
    });
});
