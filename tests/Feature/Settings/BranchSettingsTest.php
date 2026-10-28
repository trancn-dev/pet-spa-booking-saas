<?php

declare(strict_types=1);

use App\Actions\Branches\CreateBranch;
use App\Actions\Invitations\AcceptInvitation;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.3: S12.2 Cài đặt › Cơ sở (max_branches), branch + workspace switchers, S13 checklist.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop(['plan' => Plan::factory()->create(['max_branches' => 2])]);
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->shop->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->shop->tenant, fn () => Branch::query()->sole());
    $this->hours = array_fill_keys(['1', '2', '3', '4', '5', '6'], [['08:00', '12:00'], ['13:30', '20:00']]) + ['7' => []];
    $this->base = '/app/paw-test/settings/branches';
});

it('adds a branch with its stray profile and drawer, up to the plan limit', function () {
    $this->actingAs($this->owner)->get($this->base)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Settings/Branches/Index')->has('branches', 1)->where('limit', ['used' => 1, 'max' => 2]));

    $this->post($this->base, ['code' => 'q7', 'name' => 'CN Quận 7', 'opening_hours' => $this->hours])
        ->assertRedirect($this->base)->assertSessionHasNoErrors();

    $q7 = inTenant($this->shop->tenant, fn () => Branch::query()->where('code', 'Q7')->sole());
    expect($q7->opening_hours)->toBe($this->hours)
        ->and(inTenant($this->shop->tenant, fn () => Customer::query()->where('system_branch_id', $q7->id)->exists()))->toBeTrue()
        ->and(inTenant($this->shop->tenant, fn () => CashDrawer::query()->where('branch_id', $q7->id)->value('name')))->toBe('Két 1')
        ->and(inTenant($this->shop->tenant, fn () => ActivityLog::query()->where('action', 'branch.created')->count()))->toBe(1);

    $this->post($this->base, ['code' => 'Q9', 'name' => 'CN Quận 9', 'opening_hours' => $this->hours])
        ->assertSessionHasErrors(['code' => 'Gói hiện tại cho tối đa 2 cơ sở. Liên hệ PawBook để nâng gói.']);
});

it('validates the code and the opening hours', function () {
    $this->actingAs($this->owner);

    $this->post($this->base, ['code' => 'Q1', 'name' => 'Trùng', 'opening_hours' => $this->hours])->assertSessionHasErrors(['code' => 'Mã cơ sở đã có.']);
    $this->post($this->base, ['code' => 'Q 2', 'name' => 'Sai', 'opening_hours' => $this->hours])->assertSessionHasErrors('code');
    $this->post($this->base, ['code' => 'Q2', 'name' => 'Ngược giờ', 'opening_hours' => ['1' => [['20:00', '08:00']]] + $this->hours])
        ->assertSessionHasErrors(['opening_hours.1.0.1' => 'Giờ đóng cửa phải sau giờ mở cửa.']);
});

it('edits a branch but never its code, and keeps one branch active', function () {
    $this->actingAs($this->owner)
        ->put("{$this->base}/{$this->q1->public_id}", ['code' => 'ZZ', 'name' => 'CN Quận 1 mới', 'opening_hours' => $this->hours, 'is_active' => true])
        ->assertSessionHasNoErrors();

    expect($this->q1->fresh()->name)->toBe('CN Quận 1 mới')->and($this->q1->fresh()->code)->toBe('Q1');

    $this->put("{$this->base}/{$this->q1->public_id}", ['name' => 'X', 'opening_hours' => $this->hours, 'is_active' => false])
        ->assertSessionHasErrors(['is_active' => 'Shop cần ít nhất một cơ sở đang hoạt động.']);
});

it('deletes a branch only when nobody has it as primary and another one is left', function () {
    $q7 = inTenant($this->shop->tenant, fn () => app(CreateBranch::class)->handle(['code' => 'Q7', 'name' => 'CN Quận 7']));
    $this->actingAs($this->owner);

    // The owner's primary branch is Q1.
    $this->delete("{$this->base}/{$this->q1->public_id}")->assertSessionHasErrors('branch');
    $this->delete("{$this->base}/{$q7->public_id}")->assertSessionHasNoErrors();

    expect(inTenant($this->shop->tenant, fn () => Branch::query()->pluck('code')->all()))->toBe(['Q1']);
});

it('keeps branch settings to branches.manage and to the open shop', function () {
    $receptionist = memberWithRoles($this->shop->tenant, ['receptionist'], [$this->q1]);
    $this->actingAs($receptionist)->get($this->base)->assertForbidden();
    $this->post($this->base, ['code' => 'Q7', 'name' => 'X', 'opening_hours' => $this->hours])->assertForbidden();

    $other = provisionShop(['slug' => 'other', 'ownerEmail' => 'o@other.test']);
    $foreign = inTenant($other->tenant, fn () => Branch::query()->sole());
    $this->actingAs($this->owner)->get("{$this->base}/{$foreign->public_id}")->assertNotFound();
    $this->post("/app/paw-test/branch/{$foreign->public_id}")->assertNotFound();
});

it('switches between the branches a person works at, and only those', function () {
    $q7 = inTenant($this->shop->tenant, fn () => app(CreateBranch::class)->handle(['code' => 'Q7', 'name' => 'CN Quận 7']));
    $q9 = inTenant($this->shop->tenant, fn () => app(CreateBranch::class)->handle(['code' => 'Q9', 'name' => 'CN Quận 9']));
    $groomer = memberWithRoles($this->shop->tenant, ['groomer'], [$q7, $this->q1]);

    $this->actingAs($groomer)->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('currentBranch.code', 'Q7')->where('branches', fn ($b) => collect($b)->pluck('code')->all() === ['Q1', 'Q7']));

    $this->post("/app/paw-test/branch/{$this->q1->public_id}")->assertRedirect();
    $this->get('/app/paw-test/dashboard')->assertInertia(fn (Assert $page) => $page->where('currentBranch.code', 'Q1'));

    $this->post("/app/paw-test/branch/{$q9->public_id}")->assertForbidden();

    // The owner (branches.all) can pick any branch.
    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('branches', fn ($b) => count($b) === 3));
});

it('lists the shops a person can switch to', function () {
    $other = provisionShop(['slug' => 'other', 'name' => 'Other Shop', 'ownerEmail' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($other->tenant, $other->invitation, $this->owner);

    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('workspaces', [['slug' => 'other', 'name' => 'Other Shop'], ['slug' => 'paw-test', 'name' => 'Paw Test']]));

    inTenant($other->tenant, fn () => Staff::query()->update(['status' => 'disabled']));
    $this->get('/app/paw-test/dashboard')->assertInertia(fn (Assert $page) => $page->where('workspaces', [['slug' => 'paw-test', 'name' => 'Paw Test']]));
});

it('shows the first-run checklist to the owner only', function () {
    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')
            ->has('checklist', 6)
            ->where('checklist.1', ['key' => 'services', 'label' => 'Thêm dịch vụ', 'done' => false, 'href' => null]));

    $groomer = memberWithRoles($this->shop->tenant, ['groomer'], [$this->q1]);
    $this->actingAs($groomer)->get('/app/paw-test/dashboard')->assertInertia(fn (Assert $page) => $page->where('checklist', null));
});
