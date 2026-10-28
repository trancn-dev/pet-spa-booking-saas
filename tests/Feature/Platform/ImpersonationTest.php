<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Enums\PlatformAdminRole;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Platform\ImpersonationSession;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Staff;
use App\Models\User;
use App\Platform\Impersonation;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.6 / F21: "Đăng nhập như chủ shop" (ADR 0008).
*/

beforeEach(function () {
    $this->withoutVite();
    $this->support = PlatformAdmin::factory()->create(['name' => 'Lê Hỗ Trợ', 'role' => PlatformAdminRole::Support, 'two_factor_confirmed_at' => now()]);

    $this->shop = provisionShop();
    $this->owner = User::factory()->create(['email' => 'owner@paw.test', 'name' => 'Chủ Paw']);
    app(AcceptInvitation::class)->handle($this->shop->tenant, $this->shop->invitation, $this->owner);

    // The same person also owns shop B.
    $this->b = provisionShop(['slug' => 'shop-b', 'name' => 'Shop B', 'ownerEmail' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->b->tenant, $this->b->invitation, $this->owner);

    $this->start = function (string $reason = 'Chủ shop báo lỗi in hóa đơn'): string {
        $response = $this->actingAs($this->support, 'platform')->post("/admin/tenants/{$this->shop->tenant->public_id}/impersonate", ['reason' => $reason]);
        auth()->shouldUse('web'); // the next request is the CMS tab

        return (string) $response->headers->get('Location');
    };
    $this->enter = function (): ImpersonationSession {
        $this->get(($this->start)())->assertRedirect('/app/paw-test/dashboard');

        return ImpersonationSession::query()->latest('id')->firstOrFail();
    };
});

it('lets support start a session with a reason and hands over a one-time link', function () {
    $this->actingAs($this->support, 'platform')->post("/admin/tenants/{$this->shop->tenant->public_id}/impersonate", ['reason' => ''])
        ->assertSessionHasErrors('reason');

    $url = ($this->start)();
    $session = ImpersonationSession::query()->sole();

    expect($url)->toStartWith(url('/app/paw-test/impersonate/'))
        ->and($session->token_hash)->toBe(hash('sha256', basename($url)))
        ->and($session->target_user_id)->toBe($this->owner->id)
        ->and($session->started_at)->toBeNull()
        ->and(PlatformAuditLog::query()->where('action', 'impersonation.started')->value('impersonation_session_id'))->toBe($session->id);
});

it('signs in as the owner once, with a red banner, replacing whoever was signed in', function () {
    $url = ($this->start)();
    $someone = memberWithRoles($this->shop->tenant, ['groomer']);
    $this->actingAs($someone);

    $this->get($url)->assertRedirect('/app/paw-test/dashboard')->assertHeader('Referrer-Policy', 'no-referrer');
    $this->assertAuthenticatedAs($this->owner, 'web');

    $this->get('/app/paw-test/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('impersonating.admin', 'Lê Hỗ Trợ')
        ->where('impersonating.shop', 'Paw Test')
        ->where('workspaces', [['slug' => 'paw-test', 'name' => 'Paw Test']]));

    auth('web')->logout();
    $this->flushSession();
    $this->get($url)->assertNotFound(); // used
});

it('404s an expired token or one used with another shop\'s slug', function () {
    $url = ($this->start)();
    $this->get(str_replace('/app/paw-test/', '/app/shop-b/', $url))->assertNotFound();

    $this->travel(Impersonation::TOKEN_SECONDS + 1)->seconds();
    $this->get($url)->assertNotFound();
    $this->assertGuest('web');
});

it('keeps the session inside the supported shop', function () {
    ($this->enter)();

    $this->get('/app/shop-b/dashboard')->assertNotFound();
    $this->get('/app/paw-test/dashboard')->assertOk();
});

it('refuses sensitive actions and records every other write', function () {
    $session = ($this->enter)();
    $staff = inTenant($this->shop->tenant, fn () => Staff::query()->where('user_id', $this->owner->id)->sole());

    $this->put('/settings/password', ['current_password' => 'password', 'password' => 'x', 'password_confirmation' => 'x'])->assertForbidden();
    $this->patch('/settings/profile', ['name' => 'X', 'email' => 'x@x.test'])->assertForbidden();
    $this->post('/app/paw-test/settings/roles', ['display_name' => 'X', 'permissions' => []])->assertForbidden();
    $this->patch("/app/paw-test/staff/{$staff->id}/status", ['status' => 'disabled'])->assertForbidden();

    $this->post('/app/paw-test/settings/branches', ['code' => 'Q7', 'name' => 'CN Quận 7', 'opening_hours' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], [])])
        ->assertRedirect('/app/paw-test/settings/branches');

    $writes = PlatformAuditLog::query()->where('action', 'impersonation.write')->get();
    expect($writes)->toHaveCount(1)
        ->and($writes[0]->changes['route'])->toBe('app.settings.branches.store')
        ->and($writes[0]->platform_admin_id)->toBe($this->support->id)
        ->and($writes[0]->impersonation_session_id)->toBe($session->id)
        ->and(inTenant($this->shop->tenant, fn () => ActivityLog::query()->where('action', 'branch.created')->value('impersonation_session_id')))->toBe($session->id)
        ->and(inTenant($this->shop->tenant, fn () => Branch::query()->count()))->toBe(2);
});

it('ends after 30 minutes or on "Thoát"', function () {
    $session = ($this->enter)();

    $this->travel(Impersonation::SESSION_MINUTES + 1)->minutes();
    $this->get('/app/paw-test/dashboard')->assertRedirect('/login');
    $this->assertGuest('web');
    expect($session->fresh()->ended_at)->not->toBeNull();

    $this->travelBack();
    $second = ($this->enter)();
    $this->post('/impersonation/exit')->assertRedirect('/login');
    $this->assertGuest('web');
    expect($second->fresh()->ended_at)->not->toBeNull()
        ->and(PlatformAuditLog::query()->where('action', 'impersonation.ended')->count())->toBe(2);
});

it('shows each owner only their own shop\'s support history, and A4 lists it', function () {
    ($this->enter)();
    $this->post('/impersonation/exit');

    $this->actingAs($this->owner)->get('/app/paw-test/settings/support-access')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Settings/SupportAccess')->has('sessions', 1)
            ->where('sessions.0.admin', 'Lê Hỗ Trợ')->where('sessions.0.state', 'ended'));
    $this->get('/app/shop-b/settings/support-access')->assertInertia(fn (Assert $page) => $page->has('sessions', 0));

    $groomer = memberWithRoles($this->shop->tenant, ['groomer']);
    $this->actingAs($groomer)->get('/app/paw-test/settings/support-access')->assertForbidden();

    $this->actingAs($this->support, 'platform')->get("/admin/tenants/{$this->shop->tenant->public_id}?tab=support")
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'support')->has('sessions', 1)->where('sessions.0.reason', 'Chủ shop báo lỗi in hóa đơn'));
});
