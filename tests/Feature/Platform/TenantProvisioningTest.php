<?php

declare(strict_types=1);

use App\Authorization\PermissionCatalog;
use App\Enums\PlatformAdminRole;
use App\Enums\TenantStatus;
use App\Mail\ShopInvitationMail;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| A3 create a shop (super-admin), A4 overview + resend / copy the owner invitation (F19).
*/

beforeEach(function () {
    $this->withoutVite();
    PermissionCatalog::sync();
    Mail::fake();

    $this->super = PlatformAdmin::factory()->create(['name' => 'Nguyễn Văn A', 'two_factor_confirmed_at' => now()]);
    $this->support = PlatformAdmin::factory()->create(['role' => PlatformAdminRole::Support, 'two_factor_confirmed_at' => now()]);
    $this->pro = Plan::factory()->create(['code' => 'pro', 'name' => 'Pro', 'max_branches' => 3]);

    $this->payload = [
        'name' => 'Paw House Spa & Vet', 'slug' => 'paw-house', 'kind' => 'both', 'timezone' => 'Asia/Ho_Chi_Minh',
        'branch_code' => 'q3', 'branch_name' => 'CN Quận 3', 'branch_address' => '123 Võ Văn Tần',
        'owner_name' => 'Trần Minh Khoa', 'owner_email' => 'khoa@pawhouse.test', 'owner_phone' => '+84903000111',
        'plan_id' => $this->pro->id, 'billing' => 'paid', 'paid_from' => '2026-11-01', 'paid_until' => '2027-10-31',
        'amount' => '6000000', 'payment_ref' => 'CK-PAW', 'note' => 'Bán qua hotline',
    ];
});

it('sells a shop from the wizard, mails the owner and opens A4 with the link', function () {
    $this->actingAs($this->super, 'platform')->get('/admin/tenants/create')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Tenants/Create')->has('plans', 1)->where('defaults.trial_days', 14));

    $response = $this->post('/admin/tenants', $this->payload);

    $tenant = Tenant::query()->where('slug', 'paw-house')->sole();
    $response->assertRedirect("/admin/tenants/{$tenant->public_id}")->assertSessionHas('invite_url');

    expect($tenant->status)->toBe(TenantStatus::Active)
        // 31/10/2027 23:59:59 in Ho Chi Minh City, stored in UTC.
        ->and(DB::table('tenants')->where('id', $tenant->id)->value('plan_expires_at'))->toBe('2027-10-31 16:59:59')
        ->and($tenant->admin_note)->toBe('Bán qua hotline');
    Mail::assertQueued(ShopInvitationMail::class, fn ($mail) => $mail->hasTo('khoa@pawhouse.test'));

    $this->get("/admin/tenants/{$tenant->public_id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Tenants/Show')
            ->where('tenant.invitation.state', 'pending')
            ->where('tenant.invitation.state_label', 'Chưa nhận lời mời')
            ->where('tenant.usage.0.used', 1)
            ->where('tenant.usage.0.max', 3)
            ->where('can.manage', true)
            ->has('inviteUrl'));
});

it('starts a trial and checks the slug as it is typed', function () {
    $this->actingAs($this->super, 'platform');

    $this->getJson('/admin/tenants/slug-check?slug=admin')->assertJson(['available' => false, 'message' => 'Địa chỉ này đã được hệ thống dùng, chọn tên khác.']);
    $this->getJson('/admin/tenants/slug-check?slug=paw-house')->assertJson(['available' => true]);

    $this->post('/admin/tenants', [...$this->payload, 'billing' => 'trial', 'trial_days' => 30])->assertSessionHasNoErrors();

    $this->getJson('/admin/tenants/slug-check?slug=paw-house')->assertJson(['available' => false, 'message' => 'Địa chỉ này đã có shop dùng.']);
    expect(Tenant::query()->sole()->status)->toBe(TenantStatus::Trial);
});

it('rejects a reserved slug and a sale without dates', function () {
    $this->actingAs($this->super, 'platform')
        ->post('/admin/tenants', [...$this->payload, 'slug' => 'login', 'paid_until' => null])
        ->assertSessionHasErrors(['slug', 'paid_until']);

    expect(Tenant::query()->count())->toBe(0);
});

it('resends the owner invitation with a new token and kills the old link', function () {
    $shop = provisionShop(admin: $this->super);
    $old = $shop->token;

    $this->actingAs($this->super, 'platform')
        ->post("/admin/tenants/{$shop->tenant->public_id}/invitation")
        ->assertSessionHas('invite_url');

    $invitation = inTenant($shop->tenant, fn () => Invitation::query()->sole());
    expect($invitation->token_hash)->not->toBe(hash('sha256', $old))
        ->and(PlatformAuditLog::query()->where('action', 'tenant.invitation_resent')->count())->toBe(1);
    Mail::assertQueued(ShopInvitationMail::class, 1);

    $this->get("/invite/{$old}")->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));

    // "Sao chép link": a new link, no mail.
    $this->post("/admin/tenants/{$shop->tenant->public_id}/invitation", ['link_only' => true])->assertSessionHas('invite_url');
    Mail::assertQueued(ShopInvitationMail::class, 1);
});

it('lets support look but not create or resend', function () {
    $shop = provisionShop(admin: $this->super);

    $this->actingAs($this->support, 'platform');
    $this->get('/admin/tenants/create')->assertForbidden();
    $this->post('/admin/tenants', $this->payload)->assertForbidden();
    $this->post("/admin/tenants/{$shop->tenant->public_id}/invitation")->assertForbidden();

    $this->get("/admin/tenants/{$shop->tenant->public_id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
});

it('refuses to resend once the owner has joined', function () {
    $shop = provisionShop(admin: $this->super);
    $this->post("/invite/{$shop->token}", ['name' => 'Chủ', 'password' => 'password', 'password_confirmation' => 'password']);

    $this->actingAs($this->super, 'platform')
        ->post("/admin/tenants/{$shop->tenant->public_id}/invitation")
        ->assertSessionHasErrors(['invitation' => 'Chủ shop đã nhận lời mời.']);
});
