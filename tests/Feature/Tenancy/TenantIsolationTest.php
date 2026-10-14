<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/*
| MANDATORY: these tests guard the core multi-tenant guarantee.
| Never delete or weaken them; extend them when adding tenant-owned models.
*/

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'b']);

    $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'email' => 'same@example.com']);
    $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id, 'email' => 'same@example.com']);

    $this->asTenant = fn (Tenant $t, Closure $fn) => app(CurrentTenant::class)->run($t, $fn);
});

it('tenant A cannot read tenant B data (all/count/find/first/where)', function () {
    ($this->asTenant)($this->tenantA, function () {
        expect(User::all()->pluck('id')->all())->toBe([$this->userA->id])
            ->and(User::count())->toBe(1)
            ->and(User::find($this->userB->id))->toBeNull()
            ->and(User::query()->whereKey($this->userB->id)->first())->toBeNull()
            ->and(User::where('email', 'same@example.com')->pluck('id')->all())->toBe([$this->userA->id])
            ->and(fn () => User::findOrFail($this->userB->id))->toThrow(ModelNotFoundException::class);
    });

    ($this->asTenant)($this->tenantB, function () {
        expect(User::all()->pluck('id')->all())->toBe([$this->userB->id])
            ->and(User::find($this->userA->id))->toBeNull();
    });
});

it('tenant A cannot update or delete tenant B data', function () {
    ($this->asTenant)($this->tenantA, function () {
        expect(User::whereKey($this->userB->id)->update(['name' => 'hacked']))->toBe(0)
            ->and(User::whereKey($this->userB->id)->delete())->toBe(0);
    });

    expect(User::withoutGlobalScopes()->find($this->userB->id))
        ->not->toBeNull()
        ->name->not->toBe('hacked');
});

it('stamps tenant_id from the current tenant on create', function () {
    $user = ($this->asTenant)($this->tenantA, fn () => User::factory()->create(['tenant_id' => null, 'email' => 'new@example.com']));

    expect($user->tenant_id)->toBe($this->tenantA->id);
});

it('refuses to create a record for another tenant', function () {
    ($this->asTenant)($this->tenantA, fn () => User::factory()->create(['tenant_id' => $this->tenantB->id]));
})->throws(LogicException::class);

it('refuses to move a record to another tenant', function () {
    ($this->asTenant)($this->tenantA, function () {
        $this->userA->tenant_id = $this->tenantB->id;
        $this->userA->save();
    });
})->throws(LogicException::class, 'immutable');

it('fails closed when no tenant is resolved', function () {
    User::query()->get();
})->throws(TenantNotResolvedException::class);

it('only sees across tenants through the explicit escape hatch', function () {
    $count = app(CurrentTenant::class)->withoutTenancy(fn () => User::count());

    expect($count)->toBe(2);
});
