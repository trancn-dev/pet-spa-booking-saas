<?php

declare(strict_types=1);

use App\Actions\Branches\CreateBranch;
use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Staff\StaffQuota;
use App\Mail\ShopInvitationMail;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\InvitationLookup;
use App\Tenancy\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.4: the owner invites staff (S11), within plans.max_staff.
*/

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();

    $this->plan = Plan::factory()->create(['max_staff' => 3]);
    $this->shop = provisionShop(['plan' => $this->plan]);
    $this->owner = User::factory()->create(['email' => 'owner@paw.test', 'name' => 'Chủ Shop']);
    app(AcceptInvitation::class)->handle($this->shop->tenant, $this->shop->invitation, $this->owner);

    $this->q1 = inTenant($this->shop->tenant, fn () => Branch::query()->sole());
    $this->q7 = inTenant($this->shop->tenant, fn () => app(CreateBranch::class)->handle(['code' => 'Q7', 'name' => 'CN Quận 7']));

    $this->invite = fn (array $overrides = []) => $this->actingAs($this->owner)->post('/app/paw-test/staff/invitations', [
        'email' => 'mai@paw.test',
        'roles' => ['receptionist', 'cashier'],
        'primary_branch' => $this->q1->public_id,
        'branches' => [$this->q7->public_id],
        ...$overrides,
    ]);
    $this->tokenFor = function (string $email): string {
        $mail = null;
        Mail::assertQueued(ShopInvitationMail::class, function (ShopInvitationMail $m) use ($email, &$mail) {
            if ($m->hasTo($email)) {
                $mail = $m;
            }

            return $m->hasTo($email);
        });

        return basename($mail->url);
    };
});

it('invites staff by e-mail with roles and branches, then they join', function () {
    ($this->invite)()->assertRedirect('/app/paw-test/staff?tab=invitations')->assertSessionHasNoErrors();

    $invitation = inTenant($this->shop->tenant, fn () => Invitation::query()->where('email', 'mai@paw.test')->sole());
    $ownerStaff = inTenant($this->shop->tenant, fn () => Staff::query()->where('user_id', $this->owner->id)->sole());
    expect($invitation->role_names)->toBe(['receptionist', 'cashier'])
        ->and($invitation->primary_branch_id)->toBe($this->q1->id)
        ->and($invitation->branch_ids)->toBe([$this->q7->id])
        ->and($invitation->invited_by_staff_id)->toBe($ownerStaff->id)
        ->and(inTenant($this->shop->tenant, fn () => ActivityLog::query()->where('action', 'invitation.created')->count()))->toBe(1);

    Mail::assertQueued(ShopInvitationMail::class, fn ($m) => $m->hasTo('mai@paw.test') && $m->roleLabel === 'Lễ tân, Thu ngân' && $m->invitedBy === 'Chủ Shop');
    $token = ($this->tokenFor)('mai@paw.test');
    expect($invitation->token_hash)->toBe(hash('sha256', $token));

    $this->get('/app/paw-test/staff?tab=invitations')->assertInertia(fn (Assert $page) => $page->component('App/Staff/Index')
        ->where('tab', 'invitations')->has('invitations', 1)->where('invitations.0.roles', ['Lễ tân', 'Thu ngân'])->where('quota', ['used' => 2, 'max' => 3]));

    auth('web')->logout();
    $this->post("/invite/{$token}", ['name' => 'Võ Thị Mai', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertRedirect('/app/paw-test');

    $mai = User::query()->where('email', 'mai@paw.test')->sole();
    $staff = inTenant($this->shop->tenant, fn () => Staff::query()->with('branches')->where('user_id', $mai->id)->sole());
    expect($staff->primary_branch_id)->toBe($this->q1->id)
        ->and($staff->branches->pluck('code')->sort()->values()->all())->toBe(['Q1', 'Q7'])
        ->and(asTeam($this->shop->tenant, fn () => $mai->fresh()->getRoleNames()->sort()->values()->all()))->toBe(['cashier', 'receptionist']);
});

it('refuses the owner role, unknown roles, foreign branches and current staff', function (array $overrides, string $field, string $message) {
    ($this->invite)($overrides)->assertSessionHasErrors([$field => $message]);

    expect(inTenant($this->shop->tenant, fn () => Invitation::query()->whereNull('invited_by_admin_id')->count()))->toBe(0);
})->with([
    'owner role' => [['roles' => ['owner']], 'roles', 'Không mời được role Chủ shop.'],
    'unknown role' => [['roles' => ['wizard']], 'roles', 'Role không tồn tại.'],
    'branch of another shop' => [['primary_branch' => '01jzzzzzzzzzzzzzzzzzzzzzzz'], 'primary_branch', 'Chọn cơ sở chính đang hoạt động.'],
    'already staff' => [['email' => 'owner@paw.test'], 'email', 'Người này đã là nhân viên của shop.'],
]);

it('counts accounts plus pending invitations against max_staff', function () {
    // owner (1) + 2 invitations = 3
    ($this->invite)(['email' => 'a@paw.test'])->assertSessionHasNoErrors();
    ($this->invite)(['email' => 'b@paw.test'])->assertSessionHasNoErrors();
    ($this->invite)(['email' => 'c@paw.test'])->assertSessionHasErrors(['email' => StaffQuota::FULL]);

    // Staff without an account are free.
    $this->post('/app/paw-test/staff', ['display_name' => 'Lê Văn Tư', 'title' => 'Bảo vệ', 'primary_branch' => $this->q1->public_id])
        ->assertSessionHasNoErrors();

    // Re-inviting the same address replaces the pending invitation instead of taking a seat.
    ($this->invite)(['email' => 'b@paw.test', 'roles' => ['groomer']])->assertSessionHasNoErrors();

    $b = inTenant($this->shop->tenant, fn () => Invitation::query()->where('email', 'b@paw.test')->whereNull('revoked_at')->sole());
    $this->delete("/app/paw-test/staff/invitations/{$b->id}")->assertSessionHasNoErrors();
    ($this->invite)(['email' => 'c@paw.test'])->assertSessionHasNoErrors();
});

it('checks the quota again when the invitation is accepted', function () {
    ($this->invite)()->assertSessionHasNoErrors();
    $token = ($this->tokenFor)('mai@paw.test');
    $this->plan->update(['max_staff' => 1]); // downgraded meanwhile

    auth('web')->logout();
    $this->post("/invite/{$token}", ['name' => 'Mai', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors(['invitation' => StaffQuota::FULL]);

    expect(User::query()->where('email', 'mai@paw.test')->exists())->toBeFalse();
});

it('locks the shop row before the invitation when accepting, so parallel accepts queue up', function () {
    ($this->invite)()->assertSessionHasNoErrors();
    $token = ($this->tokenFor)('mai@paw.test');
    $mai = User::factory()->create(['email' => 'mai@paw.test']);
    [$invitation] = app(InvitationLookup::class)->find($token);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(AcceptInvitation::class)->handle($this->shop->tenant, $invitation, $mai);
    $locks = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'for update'))->values();

    expect($locks[0])->toContain('`tenants`')->and($locks[1])->toContain('`invitations`');
})->group('mysql');

it('resends with a new token and revokes', function () {
    ($this->invite)()->assertSessionHasNoErrors();
    $old = ($this->tokenFor)('mai@paw.test');
    $invitation = inTenant($this->shop->tenant, fn () => Invitation::query()->where('email', 'mai@paw.test')->sole());

    $this->post("/app/paw-test/staff/invitations/{$invitation->id}/resend")->assertSessionHasNoErrors();
    Mail::assertQueued(ShopInvitationMail::class, 2);
    $this->get("/invite/{$old}")->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    $this->delete("/app/paw-test/staff/invitations/{$invitation->id}")->assertSessionHasNoErrors();
    $this->post("/app/paw-test/staff/invitations/{$invitation->id}/resend")->assertSessionHasErrors('invitation');
    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

it('rejects an invitation opened with another account', function () {
    ($this->invite)()->assertSessionHasNoErrors();
    $token = ($this->tokenFor)('mai@paw.test');

    $this->actingAs(User::factory()->create(['email' => 'nope@paw.test']))->post("/invite/{$token}")->assertSessionHasErrors('email');
});

it('gives a person staff of shop A a second workspace when they join shop B', function () {
    $other = provisionShop(['slug' => 'other', 'name' => 'Other', 'ownerEmail' => 'boss@other.test']);
    $mai = memberWithRoles($other->tenant, ['groomer']);
    $mai->forceFill(['email' => 'mai@paw.test'])->save();

    ($this->invite)()->assertSessionHasNoErrors();
    $token = ($this->tokenFor)('mai@paw.test');
    $this->actingAs($mai)->post("/invite/{$token}")->assertRedirect('/app/paw-test');

    expect(app(TenantMembership::class)->tenantsFor($mai)->pluck('slug')->sort()->values()->all())->toBe(['other', 'paw-test']);
});

it('keeps invitations inside their shop and to staff.manage', function () {
    $other = provisionShop(['slug' => 'other', 'ownerEmail' => 'boss@other.test']);
    $foreign = $other->invitation; // the other shop's (owner) invitation

    $this->actingAs($this->owner)->post("/app/paw-test/staff/invitations/{$foreign->id}/resend")->assertNotFound();
    $this->delete("/app/paw-test/staff/invitations/{$foreign->id}")->assertNotFound();

    $manager = memberWithRoles($this->shop->tenant, ['branch_manager'], [$this->q1]);
    $this->actingAs($manager)->get('/app/paw-test/staff')->assertOk()->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
    $this->post('/app/paw-test/staff/invitations', ['email' => 'x@paw.test', 'roles' => ['groomer'], 'primary_branch' => $this->q1->public_id])->assertForbidden();
});
