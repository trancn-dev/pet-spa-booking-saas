<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Enums\PlatformAdminRole;
use App\Enums\TenantStatus;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Platform\TenantSubscription;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Actions\ExtendSubscription;
use App\Platform\Mail\PlanExpiringMail;
use App\Platform\TenantStatusChange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 4.5: plan lifecycle (db-schema 2.1, F20): expiry job, extension, suspension (read-only).
*/

beforeEach(function () {
    $this->withoutVite();
    Mail::fake();
    Cache::flush();
    CarbonImmutable::setTestNow('2026-11-10 03:00:00'); // 10:00 in Ho Chi Minh City

    $this->super = PlatformAdmin::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->support = PlatformAdmin::factory()->create(['role' => PlatformAdminRole::Support, 'two_factor_confirmed_at' => now()]);
    $this->pro = Plan::factory()->create(['code' => 'pro', 'name' => 'Pro']);
    $this->shop = provisionShop(['plan' => $this->pro], $this->super);
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);

    $this->set = fn (TenantStatus $status, string $expires, array $extra = []) => $this->tenant->forceFill(['status' => $status, 'plan_expires_at' => $expires, ...$extra])->save();
    $this->audits = fn (string $action) => PlatformAuditLog::query()->where('tenant_id', $this->tenant->id)->where('action', $action)->count();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('keeps a suspended shop readable but refuses every write with 423', function () {
    ($this->set)(TenantStatus::Suspended, '2026-12-31 00:00:00', ['suspended_reason' => 'Chưa thanh toán']);
    $q1 = inTenant($this->tenant, fn () => Branch::query()->sole());

    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('readOnly', true)->where('planNotice.level', 'danger'));
    $this->get('/app/paw-test/settings/branches')->assertOk();

    $this->post('/app/paw-test/settings/branches', ['code' => 'Q7', 'name' => 'CN Quận 7', 'opening_hours' => []])
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page->component('Errors/ShopSuspended')->where('reason', 'Chưa thanh toán'));
    $this->put("/app/paw-test/settings/branches/{$q1->public_id}", ['name' => 'X'])->assertStatus(423);

    // Picking the topbar branch only touches the session.
    $this->post("/app/paw-test/branch/{$q1->public_id}")->assertRedirect();

    expect(inTenant($this->tenant, fn () => Branch::query()->count()))->toBe(1);
});

it('404s a cancelled shop', function () {
    ($this->set)(TenantStatus::Cancelled, '2026-12-31 00:00:00');

    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')->assertNotFound();
});

it('warns in the CMS before expiry and while past due', function () {
    ($this->set)(TenantStatus::Active, '2026-11-14 16:59:59');
    $this->actingAs($this->owner)->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('planNotice', ['level' => 'warning', 'text' => 'Gói hết hạn ngày 14/11/2026 (còn 5 ngày). Liên hệ PawBook để gia hạn.']));

    ($this->set)(TenantStatus::PastDue, '2026-11-07 16:59:59');
    $this->get('/app/paw-test/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('planNotice', ['level' => 'danger', 'text' => 'Gói đã hết hạn ngày 07/11/2026. Shop sẽ bị tạm khóa sau 5 ngày nếu chưa gia hạn.'])->where('readOnly', false));

    ($this->set)(TenantStatus::Active, '2027-11-10 00:00:00');
    $this->get('/app/paw-test/dashboard')->assertInertia(fn (Assert $page) => $page->where('planNotice', null));
});

it('reminds once, marks past due, then suspends after the grace period', function () {
    // 5 days left → one reminder, even if the job runs twice.
    ($this->set)(TenantStatus::Active, '2026-11-14 16:59:59');
    $this->artisan('tenants:check-expiry')->assertSuccessful();
    $this->artisan('tenants:check-expiry')->assertSuccessful();
    Mail::assertQueued(PlanExpiringMail::class, 1);
    Mail::assertQueued(PlanExpiringMail::class, fn ($m) => $m->hasTo('owner@paw.test') && $m->daysLeft === 5 && $m->expiresOn === '14/11/2026');
    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active);

    // Expired yesterday → past due (still usable).
    CarbonImmutable::setTestNow('2026-11-15 03:00:00');
    $this->artisan('tenants:check-expiry');
    expect($this->tenant->fresh()->status)->toBe(TenantStatus::PastDue)->and(($this->audits)('tenant.past_due'))->toBe(1);

    // Day 6 of grace: nothing; day 7 over: suspended for expiry.
    CarbonImmutable::setTestNow('2026-11-20 03:00:00');
    $this->artisan('tenants:check-expiry');
    expect($this->tenant->fresh()->status)->toBe(TenantStatus::PastDue);

    CarbonImmutable::setTestNow('2026-11-21 17:10:00');
    $this->artisan('tenants:check-expiry');
    $tenant = $this->tenant->fresh();
    expect($tenant->status)->toBe(TenantStatus::Suspended)
        ->and($tenant->suspended_reason)->toBe(TenantStatusChange::EXPIRED_REASON)
        ->and(($this->audits)('tenant.suspended'))->toBe(1);
});

it('never lets the job and an admin overwrite each other', function () {
    ($this->set)(TenantStatus::Active, '2026-11-09 00:00:00');
    $seenByJob = $this->tenant->fresh(); // the job read "active"…

    // …an admin suspends the shop meanwhile…
    $this->actingAs($this->super, 'platform')->post("/admin/tenants/{$this->tenant->public_id}/suspend", ['reason' => 'Vi phạm điều khoản'])->assertSessionHasNoErrors();

    // …so the job's "active → past due" no longer applies.
    expect(TenantStatusChange::apply($seenByJob, TenantStatus::Active, TenantStatus::PastDue, 'tenant.past_due'))->toBeFalse()
        ->and($this->tenant->fresh()->status)->toBe(TenantStatus::Suspended)
        ->and($this->tenant->fresh()->suspended_reason)->toBe('Vi phạm điều khoản')
        ->and(($this->audits)('tenant.past_due'))->toBe(0)
        ->and(($this->audits)('tenant.suspended'))->toBe(1);
});

it('reopens an expiry-suspended shop at once when extended, with one audit row', function () {
    ($this->set)(TenantStatus::Suspended, '2026-11-01 16:59:59', ['suspended_at' => now(), 'suspended_reason' => TenantStatusChange::EXPIRED_REASON]);

    $this->actingAs($this->super, 'platform')
        ->post("/admin/tenants/{$this->tenant->public_id}/extend", ['plan_id' => $this->pro->id, 'months' => 12, 'amount' => '6000000', 'payment_ref' => 'CK-1'])
        ->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();
    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->suspended_reason)->toBeNull()
        ->and($tenant->plan_expires_at?->toDateTimeString())->toBe('2027-11-10 03:00:00') // expired: counts from now
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->latest('id')->first()->payment_ref)->toBe('CK-1')
        ->and(($this->audits)('tenant.extended'))->toBe(1);

    $this->actingAs($this->owner)->post('/app/paw-test/settings/branches', ['code' => 'Q7', 'name' => 'CN Quận 7', 'opening_hours' => array_fill_keys(['1', '2', '3', '4', '5', '6', '7'], [])])
        ->assertRedirect('/app/paw-test/settings/branches');
});

it('continues a valid plan from its expiry and keeps an admin suspension', function () {
    ($this->set)(TenantStatus::Active, '2026-12-01 00:00:00');
    app(ExtendSubscription::class)->handle($this->super, $this->tenant, $this->pro, 3, '1500000');
    expect($this->tenant->fresh()->plan_expires_at?->toDateTimeString())->toBe('2027-03-01 00:00:00');

    ($this->set)(TenantStatus::Suspended, '2027-03-01 00:00:00', ['suspended_reason' => 'Vi phạm điều khoản']);
    app(ExtendSubscription::class)->handle($this->super, $this->tenant, $this->pro, 1, '500000');
    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Suspended);
});

it('suspends and resumes from A4 with one audit row each; support only looks', function () {
    $url = "/admin/tenants/{$this->tenant->public_id}";

    $this->actingAs($this->support, 'platform')->post("{$url}/suspend", ['reason' => 'x'])->assertForbidden();
    $this->post("{$url}/extend", ['plan_id' => $this->pro->id, 'months' => 1, 'amount' => '1'])->assertForbidden();
    $this->get("{$url}?tab=billing")->assertOk()->assertInertia(fn (Assert $page) => $page->where('tab', 'billing')->has('subscriptions', 1)->where('can.manage', false));

    $this->actingAs($this->super, 'platform');
    $this->post("{$url}/suspend", ['reason' => ''])->assertSessionHasErrors('reason');
    $this->post("{$url}/suspend", ['reason' => 'Chưa thanh toán'])->assertSessionHasNoErrors();
    $this->post("{$url}/suspend", ['reason' => 'Lần nữa'])->assertSessionHasErrors('reason');
    $this->post("{$url}/resume")->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->status)->toBe(TenantStatus::Active)
        ->and(($this->audits)('tenant.suspended'))->toBe(1)
        ->and(($this->audits)('tenant.resumed'))->toBe(1);

    // An expired shop is reopened by extending, not by "Mở khóa".
    ($this->set)(TenantStatus::Suspended, '2026-11-01 00:00:00');
    $this->post("{$url}/resume")->assertSessionHasErrors(['status' => 'Gói đã hết hạn: gia hạn để mở lại shop.']);
});

it('shows the shop log on A4 and everything on A5, append-only', function () {
    $this->actingAs($this->super, 'platform')->post("/admin/tenants/{$this->tenant->public_id}/suspend", ['reason' => 'Chưa thanh toán']);

    $this->get("/admin/tenants/{$this->tenant->public_id}?tab=logs")
        ->assertInertia(fn (Assert $page) => $page->where('logs.data.0.action', 'tenant.suspended')->where('logs.data.1.action', 'tenant.created'));

    $this->actingAs($this->support, 'platform')->get('/admin/audit-logs?action=tenant.suspended')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/AuditLogs/Index')->has('logs.data', 1)->where('logs.data.0.tenant.name', 'Paw Test'));

    $log = PlatformAuditLog::query()->latest('id')->first();
    expect(fn () => $log->update(['action' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('refuses to accept an invitation of a suspended shop', function () {
    $other = provisionShop(['slug' => 'locked', 'ownerEmail' => 'boss@locked.test']);
    $other->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    $this->post("/invite/{$other->token}", ['name' => 'Boss', 'password' => 'password', 'password_confirmation' => 'password'])
        ->assertSessionHasErrors('invitation');
    expect(User::query()->where('email', 'boss@locked.test')->exists())->toBeFalse();
    expect(Tenant::query()->where('slug', 'locked')->value('status'))->toBe(TenantStatus::Suspended);
});
