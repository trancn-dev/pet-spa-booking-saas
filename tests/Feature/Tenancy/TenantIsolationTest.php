<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use App\Tenancy\TenantRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;

/*
| MANDATORY: these tests guard the core multi-tenant guarantee.
| Never delete or weaken them; extend them when adding tenant-owned models.
*/

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'b']);

    $this->staffA = inTenant($this->tenantA, fn () => Staff::factory()->create(['display_name' => 'same']));
    $this->staffB = inTenant($this->tenantB, fn () => Staff::factory()->create(['display_name' => 'same']));

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

it('refuses to create a record with no tenant context, even with an explicit tenant_id', function () {
    Branch::factory()->make(['tenant_id' => $this->tenantA->id])->save();
})->throws(TenantNotResolvedException::class);

it('requires an explicit tenant_id when creating inside withoutTenancy()', function () {
    app(TenantContext::class)->withoutTenancy(fn () => Branch::factory()->make(['tenant_id' => null])->save());
})->throws(LogicException::class, 'tenant_id must be set explicitly');

it('only sees across tenants through the explicit escape hatch', function () {
    $count = app(TenantContext::class)->withoutTenancy(fn () => Staff::count());

    expect($count)->toBe(2);
});

it('re-applies tenant scoping inside runAs() even within withoutTenancy()', function () {
    $context = app(TenantContext::class);

    $ids = $context->withoutTenancy(fn () => $context->runAs($this->tenantA, fn () => Staff::pluck('id')->all()));

    expect($ids)->toBe([$this->staffA->id])
        ->and($context->isBypassed())->toBeFalse()
        ->and($context->has())->toBeFalse();
});

it('restores the previous tenant when the runAs() callback throws', function () {
    $context = app(TenantContext::class);
    $context->set($this->tenantA);

    try {
        $context->runAs($this->tenantB, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($context->id())->toBe($this->tenantA->id);
    $context->forget();
});

it('runs Tenant::forEach callbacks inside each tenant', function () {
    $seen = [];
    Tenant::forEach(function (Tenant $tenant) use (&$seen) {
        $seen[$tenant->slug] = Staff::pluck('id')->all();
    });

    expect($seen)->toBe(['a' => [$this->staffA->id], 'b' => [$this->staffB->id]]);
});

it('scopes exists / unique validation rules to the current tenant', function () {
    $branchB = inTenant($this->tenantB, fn () => Branch::query()->firstOrFail());

    inTenant($this->tenantA, function () use ($branchB) {
        $branchA = Branch::query()->firstOrFail();
        $exists = fn (int $id) => Validator::make(['id' => $id], ['id' => TenantRule::exists('branches')])->passes();
        $unique = fn (string $code) => Validator::make(['code' => $code], ['code' => TenantRule::unique('branches', 'code')])->passes();

        expect($exists($branchA->id))->toBeTrue()
            ->and($exists($branchB->id))->toBeFalse()
            ->and($unique($branchA->code))->toBeFalse()
            ->and($unique($branchB->code))->toBeTrue();
    });
});

it('prefixes cache keys with the tenant and refuses to build one outside a tenant', function () {
    expect(inTenant($this->tenantA, fn () => tenant_cache_key('slots')))->toBe("t:{$this->tenantA->id}:slots")
        ->and(fn () => tenant_cache_key('slots'))->toThrow(TenantNotResolvedException::class);
});
