<?php

declare(strict_types=1);

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\RoleController;
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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Everything below belongs to one tenant, resolved from the slug; members only.
Route::middleware('tenant')->prefix('app/{tenant:slug}')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', fn () => to_route('app.dashboard'))->name('app.home');

        Route::get('dashboard', DashboardController::class)->name('app.dashboard');

        // Topbar branch switcher
        Route::post('branch/{branch}', [BranchController::class, 'switch'])->name('app.branch.switch');

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
