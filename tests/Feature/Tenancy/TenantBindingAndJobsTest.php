<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantNotResolvedException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\RecordTenantJob;

/*
| MANDATORY: route model binding and queued jobs must stay inside the tenant.
*/

beforeEach(function () {
    Route::middleware(['web', 'tenant'])->prefix('app/{tenant:slug}')
        ->get('__branches/{branch}', fn (Tenant $tenant, Branch $branch) => $branch->name);

    $this->a = Tenant::factory()->create(['slug' => 'a']);
    $this->b = Tenant::factory()->create(['slug' => 'b']);
    $this->branchA = inTenant($this->a, fn () => Branch::factory()->create(['name' => 'branch-a']));
    $this->branchB = inTenant($this->b, fn () => Branch::factory()->create(['name' => 'branch-b']));
});

it('binds route models through the tenant scope: another shop\'s id is a 404', function () {
    $this->get("/app/a/__branches/{$this->branchA->public_id}")->assertOk()->assertSee('branch-a');
    $this->get("/app/a/__branches/{$this->branchB->public_id}")->assertNotFound();
    $this->get("/app/b/__branches/{$this->branchB->public_id}")->assertOk()->assertSee('branch-b');
});

it('runs a TenantAware job in the tenant it was dispatched from, even on a worker with no context', function () {
    config(['queue.default' => 'database']);
    RecordTenantJob::$seenTenantId = null;

    // A closure body, not `fn () => dispatch()`: PendingDispatch only queues the job when it is
    // destroyed, which for an arrow function happens after runAs() has restored the context.
    inTenant($this->a, function (): void {
        RecordTenantJob::dispatch();
    });

    expect(app(TenantContext::class)->has())->toBeFalse();

    Queue::connection('database')->pop()?->fire();

    expect(RecordTenantJob::$seenTenantId)->toBe($this->a->id)
        ->and(app(TenantContext::class)->has())->toBeFalse();
});

it('fails a TenantAware job dispatched without a tenant instead of running it unscoped', function () {
    config(['queue.default' => 'database']);

    RecordTenantJob::dispatch();

    Queue::connection('database')->pop()?->fire();
})->throws(TenantNotResolvedException::class);
