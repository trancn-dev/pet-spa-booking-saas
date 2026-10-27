<?php

declare(strict_types=1);

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

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Everything below belongs to one tenant, resolved from the slug; members only.
Route::middleware('tenant')->prefix('app/{tenant:slug}')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('dashboard', function () {
            return Inertia::render('Dashboard');
        })->name('app.dashboard');

        // S12.3 Cài đặt › Phân quyền
        Route::get('settings/roles', [RoleController::class, 'index'])->name('app.settings.roles');
        Route::post('settings/roles', [RoleController::class, 'store'])->name('app.settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('app.settings.roles.update');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('app.settings.roles.destroy');
    });
});
