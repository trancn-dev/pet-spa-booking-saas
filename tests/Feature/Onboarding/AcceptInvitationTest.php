<?php

declare(strict_types=1);

use App\Actions\Branches\CreateBranch;
use App\Actions\Invitations\AcceptInvitation;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.3 / S13: /invite/{token}.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop();
    $this->url = "/invite/{$this->shop->token}";
    $this->newAccount = ['name' => 'Trần Minh Khoa', 'password' => 'password', 'password_confirmation' => 'password'];
});

it('lets a new owner create an account, join every branch and land in the CMS', function () {
    inTenant($this->shop->tenant, fn () => app(CreateBranch::class)->handle(['code' => 'Q7', 'name' => 'CN Quận 7']));

    $this->get($this->url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Invitations/Show')
        ->where('state', 'pending')
        ->where('shop.name', 'Paw Test')
        ->where('roles', ['Chủ shop'])
        ->where('hasAccount', false));

    $this->post($this->url, $this->newAccount)->assertRedirect('/app/paw-test');

    $user = User::query()->where('email', 'owner@paw.test')->sole();
    $this->assertAuthenticatedAs($user, 'web');

    $staff = inTenant($this->shop->tenant, fn () => Staff::query()->with('branches')->where('user_id', $user->id)->sole());
    expect($staff->branches->pluck('code')->sort()->values()->all())->toBe(['Q1', 'Q7'])
        ->and($staff->title)->toBe('Chủ shop')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(asTeam($this->shop->tenant, fn () => $user->fresh()->hasRole('owner')))->toBeTrue();

    $this->get('/app/paw-test')->assertRedirect('/app/paw-test/dashboard');
    $this->get('/app/paw-test/dashboard')->assertOk();
});

it('asks an existing account to sign in with the invited e-mail first', function () {
    $user = User::factory()->create(['email' => 'owner@paw.test']);

    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('hasAccount', true));
    $this->post($this->url, $this->newAccount)->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'owner@paw.test')->count())->toBe(1);

    // No shop yet, but a pending invitation: login works and comes back here (intended URL).
    $this->post('/login', ['email' => 'owner@paw.test', 'password' => 'password'])->assertRedirect(url($this->url));
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('signedInAs', 'owner@paw.test'));
    $this->post($this->url)->assertRedirect('/app/paw-test');
    expect(inTenant($this->shop->tenant, fn () => Staff::query()->where('user_id', $user->id)->count()))->toBe(1);
});

it('refuses a signed-in user whose e-mail is not the invited one', function () {
    $other = User::factory()->create();

    $this->actingAs($other)->post($this->url)->assertSessionHasErrors('email');

    expect(inTenant($this->shop->tenant, fn () => Staff::query()->count()))->toBe(0)
        ->and(inTenant($this->shop->tenant, fn () => Invitation::query()->sole()->accepted_at))->toBeNull();
});

it('rejects a wrong, expired, revoked or used token', function () {
    $this->get('/invite/'.str_repeat('a', 64))->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
    $this->get('/invite/short')->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    $invitation = inTenant($this->shop->tenant, fn () => Invitation::query()->sole());

    $invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('state', 'expired')->where('message', 'Link đã hết hạn, liên hệ người mời.'));
    $this->post($this->url, $this->newAccount)->assertSessionHasErrors('invitation');

    $invitation->forceFill(['expires_at' => now()->addDay(), 'revoked_at' => now()])->save();
    $this->post($this->url, $this->newAccount)->assertSessionHasErrors('invitation');

    $invitation->forceFill(['revoked_at' => null])->save();
    $this->post($this->url, $this->newAccount)->assertRedirect('/app/paw-test');
    auth('web')->logout();

    $this->get($this->url)->assertInertia(fn (Assert $page) => $page->where('state', 'accepted'));
    $this->post($this->url, [...$this->newAccount, 'name' => 'Ai đó'])->assertSessionHasErrors('email');
    expect(User::query()->count())->toBe(1);
});

it('never creates a second staff row when accepted twice', function () {
    $user = User::factory()->create(['email' => 'owner@paw.test']);
    $accept = app(AcceptInvitation::class);

    $first = $accept->handle($this->shop->tenant, $this->shop->invitation, $user);
    $second = $accept->handle($this->shop->tenant, $this->shop->invitation, $user);

    expect($second->id)->toBe($first->id)
        ->and(inTenant($this->shop->tenant, fn () => Staff::query()->count()))->toBe(1);
});

it('never lets an invitation from staff grant the owner role', function () {
    $user = User::factory()->create(['email' => 'sneaky@paw.test']);
    $owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->shop->tenant, $this->shop->invitation, $owner);

    $invitation = inTenant($this->shop->tenant, fn () => Invitation::factory()->create([
        'email' => 'sneaky@paw.test',
        'role_names' => ['owner', 'receptionist'],
        'primary_branch_id' => Branch::query()->value('id'),
        'invited_by_staff_id' => Staff::query()->value('id'),
    ]));

    app(AcceptInvitation::class)->handle($this->shop->tenant, $invitation, $user);

    expect(asTeam($this->shop->tenant, fn () => $user->fresh()->getRoleNames()->all()))->toBe(['receptionist']);
});

it('keeps the new shop\'s people away from other shops', function () {
    $other = shopWithRoles(['slug' => 'other']);
    inTenant($other, fn () => Customer::factory()->create(['name' => 'Khách shop khác']));

    $this->post($this->url, $this->newAccount);

    $this->get('/app/other/dashboard')->assertNotFound();
    expect(inTenant($this->shop->tenant, fn () => Customer::query()->where('name', 'Khách shop khác')->exists()))->toBeFalse();
});

it('has no self sign-up', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', $this->newAccount + ['email' => 'x@paw.test'])->assertNotFound();
    expect(Tenant::query()->count())->toBe(1);
});
