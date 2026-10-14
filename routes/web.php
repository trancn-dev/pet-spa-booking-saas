<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Everything below belongs to a tenant: the host must resolve to one, and it
// is resolved before `auth` touches the session user.
Route::middleware('tenant')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});
