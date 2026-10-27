<?php

declare(strict_types=1);

use App\Actions\Roles\CreateDefaultRoles;
use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Authorization\PermissionCatalog;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| S12.3 and the rules of db-schema 3.1 / ADR 0009.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = shopWithRoles(['slug' => 'a']);
    $this->owner = memberWithRoles($this->shop, ['owner']);
    $this->role = fn (string $name): Role => Role::query()->where('team_id', $this->shop->id)->where('name', $name)->sole();
    // Actions run as the owner inside the shop (what the HTTP layer does).
    $this->asOwner = function (Closure $callback) {
        $this->actingAs($this->owner);

        return inTenant($this->shop, fn () => asTeam($this->shop, $callback));
    };
});

it('creates the 7 template roles once, owner without clinical permissions', function () {
    app(CreateDefaultRoles::class)->handle($this->shop);

    $roles = Role::query()->where('team_id', $this->shop->id)->orderBy('id')->pluck('display_name', 'name')->all();
    $owner = ($this->role)('owner');

    expect($roles)->toBe([
        'owner' => 'Chủ shop', 'branch_manager' => 'Quản lý chi nhánh', 'receptionist' => 'Lễ tân', 'cashier' => 'Thu ngân',
        'vet' => 'Bác sĩ', 'groomer' => 'Nhân viên spa', 'inventory' => 'Nhân viên kho',
    ])
        ->and($owner->hasPermissionTo('roles.manage'))->toBeTrue()
        ->and($owner->hasPermissionTo('inventory.view_cost'))->toBeTrue()
        ->and($owner->hasPermissionTo('medical.write'))->toBeFalse()
        ->and($owner->hasPermissionTo('visits.medical'))->toBeFalse();

    foreach (['branch_manager', 'receptionist', 'cashier', 'vet', 'groomer', 'inventory'] as $name) {
        foreach (['inventory.view_cost', 'purchase.cost', 'invoices.amend', 'branches.all', 'payroll.view', 'roles.manage'] as $sensitive) {
            expect(($this->role)($name)->hasPermissionTo($sensitive))->toBeFalse("{$name} must not have {$sensitive}");
        }
    }
});

it('never grants roles.manage to another role', function () {
    ($this->asOwner)(fn () => app(SaveRole::class)->handle(($this->role)('branch_manager'), 'Quản lý', ['roles.manage', 'staff.view'], true));
})->throws(ValidationException::class, 'Chỉ Chủ shop');

it('asks for confirmation before granting a sensitive permission, then logs it once', function () {
    $manager = ($this->role)('branch_manager');
    $permissions = [...$manager->permissions()->pluck('name')->all(), 'inventory.view_cost'];

    expect(fn () => ($this->asOwner)(fn () => app(SaveRole::class)->handle($manager, 'Quản lý chi nhánh', $permissions)))
        ->toThrow(ValidationException::class, 'Cần xác nhận');

    ($this->asOwner)(fn () => app(SaveRole::class)->handle($manager, 'Quản lý chi nhánh', $permissions, sensitiveConfirmed: true));

    $logs = inTenant($this->shop, fn () => ActivityLog::query()->where('action', 'role.sensitive_granted')->get());
    expect($manager->fresh()->hasPermissionTo('inventory.view_cost'))->toBeTrue()
        ->and($logs)->toHaveCount(1)
        ->and($logs->first()->changes)->toBe(['permission' => 'inventory.view_cost'])
        ->and($logs->first()->actor_staff_id)->not->toBeNull();

    // A branch manager now holds the cost permission (sees cost prices once GĐ2 shows them).
    $manager2 = memberWithRoles($this->shop, ['branch_manager']);
    expect(asTeam($this->shop, fn () => $manager2->hasPermissionTo('inventory.view_cost')))->toBeTrue();
});

it('renames a role without changing its stable code', function () {
    ($this->asOwner)(fn () => app(SaveRole::class)->handle(($this->role)('receptionist'), 'Lễ tân ca sáng', ['customers.view']));

    $role = ($this->role)('receptionist');
    expect($role->display_name)->toBe('Lễ tân ca sáng')
        ->and($role->permissions()->pluck('name')->all())->toBe(['customers.view']);
});

it('creates custom roles as custom_{id} and refuses to edit or delete the owner role', function () {
    $role = ($this->asOwner)(fn () => app(SaveRole::class)->handle(null, 'Tạp vụ', ['services.view']));

    expect($role->name)->toBe("custom_{$role->id}")
        ->and(fn () => ($this->asOwner)(fn () => app(SaveRole::class)->handle(($this->role)('owner'), 'Boss', [])))->toThrow(ValidationException::class)
        ->and(fn () => ($this->asOwner)(fn () => app(DeleteRole::class)->handle(($this->role)('owner'))))->toThrow(ValidationException::class);
});

it('refuses to delete a role that someone still holds', function () {
    memberWithRoles($this->shop, ['cashier']);

    ($this->asOwner)(fn () => app(DeleteRole::class)->handle(($this->role)('cashier')));
})->throws(ValidationException::class, 'Còn 1 người');

it('rejects unknown permission codes', function () {
    ($this->asOwner)(fn () => app(SaveRole::class)->handle(null, 'X', ['made.up']));
})->throws(ValidationException::class, 'không tồn tại');

it('gives new catalogue permissions to owner roles only', function () {
    config(['permissions.permissions' => [...config('permissions.permissions'), 'demo.new' => ['module' => 'settings', 'label' => 'Quyền mới']]]);

    expect(PermissionCatalog::sync()['created'])->toBe(['demo.new'])
        ->and(($this->role)('owner')->hasPermissionTo('demo.new'))->toBeTrue()
        ->and(($this->role)('branch_manager')->hasPermissionTo('demo.new'))->toBeFalse();
});

describe('S12.3 screen', function () {
    it('is for the owner only', function () {
        $this->actingAs($this->owner)->get('/app/a/settings/roles')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('App/Settings/Roles')
                ->has('roles', 7)
                ->where('roles.0.name', 'owner')
                ->where('roles.0.is_owner', true)
                ->has('catalog'));

        $this->actingAs(memberWithRoles($this->shop, ['branch_manager']))->get('/app/a/settings/roles')->assertForbidden();
    });

    it('saves a role and refuses another shop\'s role id (404)', function () {
        $groomer = ($this->role)('groomer');

        $this->actingAs($this->owner)
            ->put("/app/a/settings/roles/{$groomer->id}", ['display_name' => 'Spa', 'permissions' => ['services.view', 'visits.grooming']])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        expect($groomer->fresh()->display_name)->toBe('Spa');

        $other = shopWithRoles(['slug' => 'b']);
        $foreign = Role::query()->where('team_id', $other->id)->where('name', 'groomer')->sole();
        $this->put("/app/a/settings/roles/{$foreign->id}", ['display_name' => 'Hacked', 'permissions' => []])->assertNotFound();
        expect($foreign->fresh()->display_name)->toBe('Nhân viên spa');
    });
});

it('answers permissions for the shop being opened (owner at A, groomer at B)', function () {
    $b = shopWithRoles(['slug' => 'b']);
    $user = memberWithRoles($this->shop, ['owner']);
    memberAt($b, $user, ['groomer']);

    $this->actingAs($user)->get('/app/a/settings/roles')->assertOk();
    $this->actingAs($user)->get('/app/b/settings/roles')->assertForbidden();

    $this->get('/app/b/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('permissions', fn ($codes) => collect($codes)->contains('visits.grooming') && ! collect($codes)->contains('roles.manage')));
    $this->get('/app/a/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('permissions', fn ($codes) => collect($codes)->contains('roles.manage')));

    expect(app(TenantContext::class)->has())->toBeFalse();
});
