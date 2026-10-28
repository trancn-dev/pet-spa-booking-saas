<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Staff\StaffQuota;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.4: S11 list, S11.1 profile, staff without an account, disable / re-enable.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->plan = Plan::factory()->create(['max_staff' => 3]);
    $this->shop = provisionShop(['plan' => $this->plan]);
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->shop->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->shop->tenant, fn () => Branch::query()->sole());
    $this->staffOf = fn (User $user): Staff => inTenant($this->shop->tenant, fn () => Staff::query()->where('user_id', $user->id)->sole());
    $this->profile = fn (array $overrides = []) => [
        'display_name' => 'BS. Lê Thu Hà', 'title' => 'Bác sĩ', 'color' => '#8B5CF6',
        'license_no' => 'CCHN-1', 'qualifications' => 'Bác sĩ thú y',
        'roles' => ['vet'], 'primary_branch' => $this->q1->public_id, 'branches' => [],
        ...$overrides,
    ];
});

it('lists staff with roles and branches without N+1', function () {
    memberWithRoles($this->shop->tenant, ['vet'], [$this->q1]);
    memberWithRoles($this->shop->tenant, ['groomer'], [$this->q1]);

    $this->actingAs($this->owner)->get('/app/paw-test/staff')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Staff/Index')
            ->has('staff', 3)
            ->where('staff', fn ($rows) => collect($rows)->pluck('roles')->flatten(1)->pluck('name')->sort()->values()->all() === ['groomer', 'owner', 'vet'])
            ->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'owner'))
            ->where('quota', ['used' => 3, 'max' => 3])
            ->where('can.manage', true));
});

it('adds a person without an account, outside the quota', function () {
    $this->actingAs($this->owner)->post('/app/paw-test/staff', [
        'display_name' => 'Lê Văn Tư', 'title' => 'Bảo vệ', 'roles' => ['vet'], 'license_no' => 'X', 'primary_branch' => $this->q1->public_id,
    ])->assertRedirect('/app/paw-test/staff');

    $tu = inTenant($this->shop->tenant, fn () => Staff::query()->with('branches')->where('display_name', 'Lê Văn Tư')->sole());
    expect($tu->user_id)->toBeNull()
        ->and($tu->license_no)->toBeNull() // no account → no roles → no professional block
        ->and($tu->branches->pluck('id')->all())->toBe([$this->q1->id])
        ->and(inTenant($this->shop->tenant, fn () => app(StaffQuota::class)->usage($this->shop->tenant)['used']))->toBe(1);
});

it('edits roles, keeps the professional block only for vets, never touches the owner role', function () {
    $ha = memberWithRoles($this->shop->tenant, ['groomer'], [$this->q1]);
    $staff = ($this->staffOf)($ha);
    $this->actingAs($this->owner);

    $this->put("/app/paw-test/staff/{$staff->id}", ($this->profile)())->assertSessionHasNoErrors();
    expect($staff->fresh()->license_no)->toBe('CCHN-1')
        ->and(asTeam($this->shop->tenant, fn () => $ha->fresh()->getRoleNames()->all()))->toBe(['vet']);

    $this->put("/app/paw-test/staff/{$staff->id}", ($this->profile)(['roles' => ['groomer']]))->assertSessionHasNoErrors();
    expect($staff->fresh()->license_no)->toBeNull()->and($staff->fresh()->qualifications)->toBeNull();

    // The owner adds "vet" to themself; the owner role stays, and cannot be handed out here.
    $own = ($this->staffOf)($this->owner);
    $this->put("/app/paw-test/staff/{$own->id}", ($this->profile)(['display_name' => 'Chủ', 'roles' => ['vet', 'owner']]))->assertSessionHasNoErrors();
    $this->put("/app/paw-test/staff/{$staff->id}", ($this->profile)(['roles' => ['owner']]))->assertSessionHasErrors('roles');

    expect(asTeam($this->shop->tenant, fn () => $this->owner->fresh()->getRoleNames()->sort()->values()->all()))->toBe(['owner', 'vet'])
        ->and(asTeam($this->shop->tenant, fn () => $ha->fresh()->hasRole('owner')))->toBeFalse();
});

it('disables staff (not oneself, not the owner) and re-enables them within the quota', function () {
    $ha = memberWithRoles($this->shop->tenant, ['vet'], [$this->q1]);
    $staff = ($this->staffOf)($ha);
    $own = ($this->staffOf)($this->owner);
    $this->actingAs($this->owner);

    $this->patch("/app/paw-test/staff/{$own->id}/status", ['status' => 'disabled'])->assertSessionHasErrors(['status' => 'Không tự vô hiệu chính mình được.']);

    $this->patch("/app/paw-test/staff/{$staff->id}/status", ['status' => 'disabled'])->assertSessionHasNoErrors();
    expect($staff->fresh()->status->value)->toBe('disabled');

    // Their next request leaves the shop.
    $this->actingAs($ha)->get('/app/paw-test/dashboard')->assertRedirect('/login');

    // The seat was taken meanwhile: re-enabling waits for room.
    memberWithRoles($this->shop->tenant, ['groomer'], [$this->q1]);
    memberWithRoles($this->shop->tenant, ['groomer'], [$this->q1]);
    $this->actingAs($this->owner)->patch("/app/paw-test/staff/{$staff->id}/status", ['status' => 'active'])
        ->assertSessionHasErrors(['status' => StaffQuota::FULL]);

    $this->plan->update(['max_staff' => null]);
    $this->patch("/app/paw-test/staff/{$staff->id}/status", ['status' => 'active'])->assertSessionHasNoErrors();
    expect($staff->fresh()->status->value)->toBe('active');
});

it('refuses to disable an owner', function () {
    $partner = memberWithRoles($this->shop->tenant, ['owner'], [$this->q1]);

    $this->actingAs($this->owner)->patch('/app/paw-test/staff/'.($this->staffOf)($partner)->id.'/status', ['status' => 'disabled'])
        ->assertSessionHasErrors(['status' => 'Không vô hiệu được Chủ shop.']);
});

it('keeps profiles inside the shop and to staff.manage', function () {
    $manager = memberWithRoles($this->shop->tenant, ['branch_manager'], [$this->q1]);
    $staff = ($this->staffOf)($manager);

    $this->actingAs($manager)->get("/app/paw-test/staff/{$staff->id}/edit")->assertForbidden();
    $this->put("/app/paw-test/staff/{$staff->id}", ($this->profile)())->assertForbidden();

    $other = provisionShop(['slug' => 'other', 'ownerEmail' => 'boss@other.test']);
    $foreign = inTenant($other->tenant, fn () => Staff::factory()->create());
    $this->actingAs($this->owner)->get("/app/paw-test/staff/{$foreign->id}/edit")->assertNotFound();
    $this->patch("/app/paw-test/staff/{$foreign->id}/status", ['status' => 'disabled'])->assertNotFound();
});
