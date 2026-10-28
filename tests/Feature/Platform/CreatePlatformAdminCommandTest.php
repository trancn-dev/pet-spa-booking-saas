<?php

declare(strict_types=1);

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;

/*
| `platform:admin-create` is the only way to create the first admin.
*/

it('creates a super-admin by default', function () {
    $this->artisan('platform:admin-create', ['email' => 'Root@PawBook.test', '--name' => 'Root', '--password' => 'a-long-password'])
        ->assertSuccessful();

    $admin = PlatformAdmin::query()->sole();
    expect($admin->email)->toBe('root@pawbook.test')
        ->and($admin->role)->toBe(PlatformAdminRole::SuperAdmin)
        ->and($admin->hasTwoFactor())->toBeFalse();
});

it('creates a support admin with --role', function () {
    $this->artisan('platform:admin-create', ['email' => 's@pawbook.test', '--role' => 'support', '--name' => 'S', '--password' => 'a-long-password'])
        ->assertSuccessful();

    expect(PlatformAdmin::query()->sole()->role)->toBe(PlatformAdminRole::Support);
});

it('rejects a duplicate email, an unknown role and a short password', function () {
    PlatformAdmin::factory()->create(['email' => 'root@pawbook.test']);

    $this->artisan('platform:admin-create', ['email' => 'root@pawbook.test', '--name' => 'R', '--password' => 'a-long-password'])->assertFailed();
    $this->artisan('platform:admin-create', ['email' => 'x@pawbook.test', '--role' => 'god', '--name' => 'R', '--password' => 'a-long-password'])->assertFailed();
    $this->artisan('platform:admin-create', ['email' => 'y@pawbook.test', '--name' => 'R', '--password' => 'short'])->assertFailed();

    expect(PlatformAdmin::query()->count())->toBe(1);
});

it('has no admin sign-up page', function () {
    $this->get('/admin/register')->assertNotFound();
});
