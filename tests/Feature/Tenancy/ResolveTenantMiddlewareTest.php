<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'tenant'])->prefix('app/{tenant:slug}')
        ->get('__staff', fn () => Staff::pluck('display_name'));

    $this->a = Tenant::factory()->create(['slug' => 'a']);
    $this->b = Tenant::factory()->create(['slug' => 'b']);
    Staff::factory()->create(['tenant_id' => $this->a->id, 'display_name' => 'staff-a']);
    Staff::factory()->create(['tenant_id' => $this->b->id, 'display_name' => 'staff-b']);
});

it('serves only the data of the tenant in the URL slug', function () {
    $this->get('/app/a/__staff')->assertOk()->assertExactJson(['staff-a']);
    $this->get('/app/b/__staff')->assertOk()->assertExactJson(['staff-b']);
});

it('returns 404 for an unknown or suspended tenant', function () {
    $this->get('/app/nope/__staff')->assertNotFound();

    $this->b->update(['status' => 'suspended']);
    $this->get('/app/b/__staff')->assertNotFound();
});

it('returns 404 when a logged-in user is not a member of the tenant', function () {
    $user = User::factory()->create();
    Staff::factory()->create(['tenant_id' => $this->a->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/app/a/__staff')->assertOk();
    $this->actingAs($user)->get('/app/b/__staff')->assertNotFound();
});

it('ignores members whose staff row is disabled or deleted', function () {
    $user = User::factory()->create();
    $staff = Staff::factory()->create(['tenant_id' => $this->a->id, 'user_id' => $user->id, 'status' => 'disabled']);

    $this->actingAs($user)->get('/app/a/__staff')->assertNotFound();

    $staff->update(['status' => 'active']);
    $staff->delete();
    $this->actingAs($user)->get('/app/a/__staff')->assertNotFound();
});

it('remembers the last opened tenant', function () {
    $user = User::factory()->create();
    Staff::factory()->create(['tenant_id' => $this->a->id, 'user_id' => $user->id]);
    Staff::factory()->create(['tenant_id' => $this->b->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get('/app/b/__staff')->assertOk();

    expect($user->fresh()->last_tenant_id)->toBe($this->b->id);
});

it('does not leak tenant context between requests', function () {
    $this->get('/app/a/__staff')->assertOk();
    $this->get('/app/nope/__staff')->assertNotFound();
    $this->get('/app/b/__staff')->assertExactJson(['staff-b']);
});
