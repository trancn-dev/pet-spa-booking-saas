<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/*
| MANDATORY: these tests guard the core multi-tenant guarantee.
| Never delete or weaken them; extend them when adding tenant-owned models.
*/

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'b']);

    $this->staffA = Staff::factory()->create(['tenant_id' => $this->tenantA->id, 'display_name' => 'same']);
    $this->staffB = Staff::factory()->create(['tenant_id' => $this->tenantB->id, 'display_name' => 'same']);

    $this->asTenant = fn (Tenant $t, Closure $fn) => app(TenantContext::class)->runAs($t, $fn);
});

it('tenant A cannot read tenant B data (all/count/find/first/where)', function () {
    ($this->asTenant)($this->tenantA, function () {
        expect(Staff::all()->pluck('id')->all())->toBe([$this->staffA->id])
            ->and(Staff::count())->toBe(1)
            ->and(Staff::find($this->staffB->id))->toBeNull()
            ->and(Staff::query()->whereKey($this->staffB->id)->first())->toBeNull()
            ->and(Staff::where('display_name', 'same')->pluck('id')->all())->toBe([$this->staffA->id])
            ->and(fn () => Staff::findOrFail($this->staffB->id))->toThrow(ModelNotFoundException::class);
    });

    ($this->asTenant)($this->tenantB, function () {
        expect(Staff::all()->pluck('id')->all())->toBe([$this->staffB->id])
            ->and(Staff::find($this->staffA->id))->toBeNull();
    });
});

it('tenant A cannot update or delete tenant B data', function () {
    ($this->asTenant)($this->tenantA, function () {
        expect(Staff::whereKey($this->staffB->id)->update(['display_name' => 'hacked']))->toBe(0)
            ->and(Staff::whereKey($this->staffB->id)->delete())->toBe(0);
    });

    expect(Staff::withoutGlobalScopes()->find($this->staffB->id))
        ->not->toBeNull()
        ->display_name->not->toBe('hacked');
});

it('stamps tenant_id from the current tenant on create', function () {
    $user = ($this->asTenant)($this->tenantA, fn () => Staff::factory()->create(['tenant_id' => null]));

    expect($user->tenant_id)->toBe($this->tenantA->id);
});

it('refuses to create a record for another tenant', function () {
    ($this->asTenant)($this->tenantA, fn () => Staff::factory()->create(['tenant_id' => $this->tenantB->id]));
})->throws(LogicException::class);

it('refuses to move a record to another tenant', function () {
    ($this->asTenant)($this->tenantA, function () {
        $this->staffA->tenant_id = $this->tenantB->id;
        $this->staffA->save();
    });
})->throws(LogicException::class, 'immutable');

it('fails closed when no tenant is resolved', function () {
    Staff::query()->get();
})->throws(TenantNotResolvedException::class);

it('only sees across tenants through the explicit escape hatch', function () {
    $count = app(TenantContext::class)->withoutTenancy(fn () => Staff::count());

    expect($count)->toBe(2);
});
