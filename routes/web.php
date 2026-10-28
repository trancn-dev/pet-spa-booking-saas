<?php

declare(strict_types=1);

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Tenant\AppointmentActionController;
use App\Http\Controllers\Tenant\AppointmentController;
use App\Http\Controllers\Tenant\AvailabilityController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CustomerController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\OnlineBookingRateController;
use App\Http\Controllers\Tenant\PetController;
use App\Http\Controllers\Tenant\RoleController;
use App\Http\Controllers\Tenant\RosterController;
use App\Http\Controllers\Tenant\ServiceController;
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
        // Customers & pets (task 5.1). Literal segments before `{customer}` / `{pet}`.
        Route::get('customers', [CustomerController::class, 'index'])->name('app.customers.index');
        Route::get('customers/search', [CustomerController::class, 'search'])->name('app.customers.search');
        Route::get('customers/create', [CustomerController::class, 'create'])->name('app.customers.create');
        Route::post('customers', [CustomerController::class, 'store'])->name('app.customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('app.customers.show');
        Route::get('customers/{customer}/edit', [CustomerController::class, 'edit'])->name('app.customers.edit');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('app.customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('app.customers.destroy');
        Route::get('customers/{customer}/pets/create', [PetController::class, 'create'])->name('app.pets.create');
        Route::post('customers/{customer}/pets', [PetController::class, 'store'])->name('app.pets.store');
        Route::get('pets/size-suggestion', [PetController::class, 'sizeSuggestion'])->name('app.pets.size-suggestion');
        Route::post('pets/strays', [PetController::class, 'storeStray'])->name('app.pets.strays.store');
        Route::get('pets/{pet}', [PetController::class, 'show'])->name('app.pets.show');
        Route::get('pets/{pet}/edit', [PetController::class, 'edit'])->name('app.pets.edit');
        Route::put('pets/{pet}', [PetController::class, 'update'])->name('app.pets.update');
        Route::delete('pets/{pet}', [PetController::class, 'destroy'])->name('app.pets.destroy');

        // Services, spa combos, duration / price rules (task 5.2).
        Route::get('services', [ServiceController::class, 'index'])->name('app.services.index');
        Route::get('services/create', [ServiceController::class, 'create'])->name('app.services.create');
        Route::post('services', [ServiceController::class, 'store'])->name('app.services.store');
        Route::get('services/{service}', [ServiceController::class, 'show'])->name('app.services.show');
        Route::get('services/{service}/edit', [ServiceController::class, 'edit'])->name('app.services.edit');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('app.services.update');
        Route::delete('services/{service}', [ServiceController::class, 'destroy'])->name('app.services.destroy');
        Route::get('services/{service}/quote', [ServiceController::class, 'quote'])->name('app.services.quote');
        Route::post('services/{service}/rules', [ServiceController::class, 'storeRule'])->name('app.services.rules.store');
        Route::put('services/{service}/rules/{rule}', [ServiceController::class, 'updateRule'])->name('app.services.rules.update');
        Route::delete('services/{service}/rules/{rule}', [ServiceController::class, 'destroyRule'])->name('app.services.rules.destroy');

        // Free start times by capacity (task 5.4), JSON for S2.
        Route::get('availability', AvailabilityController::class)->name('app.availability');

        // Calendar S1, booking S2, detail S3 (task 5.7).
        Route::get('appointments', [AppointmentController::class, 'index'])->name('app.appointments.index');
        Route::get('appointments/day', [AppointmentController::class, 'day'])->name('app.appointments.day');
        Route::get('appointments/quotes', [AppointmentController::class, 'quotes'])->name('app.appointments.quotes');
        Route::get('appointments/on-duty', [AppointmentController::class, 'onDuty'])->name('app.appointments.on-duty');
        Route::post('appointments', [AppointmentController::class, 'store'])->name('app.appointments.store');
        Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('app.appointments.show');

        // Appointment lifecycle (task 5.6); `{appointment}` binds by public id.
        Route::post('appointments/{appointment}/check-in', [AppointmentActionController::class, 'checkIn'])->name('app.appointments.check-in');
        Route::post('appointments/{appointment}/cancel', [AppointmentActionController::class, 'cancel'])->name('app.appointments.cancel');
        Route::post('appointments/{appointment}/restore', [AppointmentActionController::class, 'restore'])->name('app.appointments.restore');
        Route::post('appointments/{appointment}/no-show', [AppointmentActionController::class, 'noShow'])->name('app.appointments.no-show');
        Route::post('appointments/{appointment}/reschedule', [AppointmentActionController::class, 'reschedule'])->name('app.appointments.reschedule');
        Route::post('appointments/{appointment}/items', [AppointmentActionController::class, 'addItems'])->name('app.appointments.items.store');

        // Rosters, time off, closed days (task 5.3), for the branch picked in the topbar.
        Route::get('rosters', [RosterController::class, 'index'])->name('app.rosters.index');
        Route::post('rosters/slots', [RosterController::class, 'storeSlot'])->name('app.rosters.slots.store');
        Route::put('rosters/slots/{slot}', [RosterController::class, 'updateSlot'])->name('app.rosters.slots.update');
        Route::delete('rosters/slots/{slot}', [RosterController::class, 'destroySlot'])->name('app.rosters.slots.destroy');
        Route::post('rosters/time-off', [RosterController::class, 'storeTimeOff'])->name('app.rosters.time-off.store');
        Route::delete('rosters/time-off/{timeOff}', [RosterController::class, 'destroyTimeOff'])->name('app.rosters.time-off.destroy');
        Route::post('rosters/closures', [RosterController::class, 'storeClosure'])->name('app.rosters.closures.store');
        Route::delete('rosters/closures/{closure}', [RosterController::class, 'destroyClosure'])->name('app.rosters.closures.destroy');

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
        Route::get('settings/branches/{branch}/online-rates', [OnlineBookingRateController::class, 'index'])->name('app.settings.online-rates');
        Route::post('settings/branches/{branch}/online-rates', [OnlineBookingRateController::class, 'store'])->name('app.settings.online-rates.store');
        Route::put('settings/branches/{branch}/online-rates/{rate}', [OnlineBookingRateController::class, 'update'])->name('app.settings.online-rates.update');
        Route::delete('settings/branches/{branch}/online-rates/{rate}', [OnlineBookingRateController::class, 'destroy'])->name('app.settings.online-rates.destroy');

        // S12.3 Cài đặt › Phân quyền
        Route::get('settings/roles', [RoleController::class, 'index'])->name('app.settings.roles');
        Route::post('settings/roles', [RoleController::class, 'store'])->name('app.settings.roles.store');
        Route::put('settings/roles/{role}', [RoleController::class, 'update'])->name('app.settings.roles.update');
        Route::delete('settings/roles/{role}', [RoleController::class, 'destroy'])->name('app.settings.roles.destroy');
    });
});
