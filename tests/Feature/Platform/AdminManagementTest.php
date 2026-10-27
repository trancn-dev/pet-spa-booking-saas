<?php

declare(strict_types=1);

use App\Enums\PlatformAdminRole;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use App\Models\Platform\PlatformAuditLog;
use App\Platform\Mail\PlatformAdminInvitationMail;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/*
| A6 / A7: inviting admins (super-admin only), one-time tokens, disabling instead of deleting.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->super = PlatformAdmin::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->support = PlatformAdmin::factory()->create(['role' => PlatformAdminRole::Support, 'two_factor_confirmed_at' => now()]);

    $this->invitation = function (array $overrides = []): array {
        $token = bin2hex(random_bytes(32));
        $invitation = PlatformAdminInvitation::factory()->create([
            'email' => 'new@pawbook.test',
            'token_hash' => hash('sha256', $token),
            'invited_by_admin_id' => $this->super->id,
            ...$overrides,
        ]);

        return [$token, $invitation];
    };
});

it('keeps admin management away from support admins', function () {
    $this->actingAs($this->support, 'platform');

    $this->get('/admin/admins')->assertForbidden();
    $this->post('/admin/admins/invitations', ['email' => 'x@pawbook.test', 'role' => 'support'])->assertForbidden();
    $this->patch("/admin/admins/{$this->super->id}/status", ['is_active' => false])->assertForbidden();
});

it('lists admins for a super-admin', function () {
    $this->actingAs($this->super, 'platform')->get('/admin/admins')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/admins/Index')->has('admins', 2));
});

it('invites by e-mail and stores only the token hash', function () {
    Mail::fake();

    $this->actingAs($this->super, 'platform')
        ->post('/admin/admins/invitations', ['email' => 'New@PawBook.test', 'role' => 'support'])
        ->assertSessionHasNoErrors();

    $invitation = PlatformAdminInvitation::query()->sole();
    expect($invitation->email)->toBe('new@pawbook.test')
        ->and($invitation->expires_at->diffInHours(now()->addHours(72)))->toBeLessThan(1);

    Mail::assertSent(PlatformAdminInvitationMail::class, function (PlatformAdminInvitationMail $mail) use ($invitation) {
        $token = basename(parse_url($mail->url, PHP_URL_PATH));

        return $mail->hasTo('new@pawbook.test')
            && hash('sha256', $token) === $invitation->token_hash
            && $token !== $invitation->token_hash;
    });
    expect(PlatformAuditLog::query()->where('action', 'admin.invited')->exists())->toBeTrue();
});

it('refuses to invite an existing admin', function () {
    $this->actingAs($this->super, 'platform')
        ->post('/admin/admins/invitations', ['email' => $this->support->email, 'role' => 'support'])
        ->assertSessionHasErrors('email');
});

it('accepts an invitation once, then forces 2FA setup', function () {
    [$token] = ($this->invitation)(['role' => PlatformAdminRole::Support]);

    $this->get("/admin/invite/{$token}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/auth/AcceptInvitation')->where('email', 'new@pawbook.test'));

    $this->post("/admin/invite/{$token}", ['name' => 'Admin Mới', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
        ->assertRedirect('/admin/two-factor/setup');

    $admin = PlatformAdmin::query()->where('email', 'new@pawbook.test')->sole();
    expect($admin->role)->toBe(PlatformAdminRole::Support)->and($admin->hasTwoFactor())->toBeFalse();
    $this->assertGuest('platform');
    $this->get('/admin/tenants')->assertNotFound();

    // Second use of the same token.
    $this->get("/admin/invite/{$token}")->assertNotFound();
    $this->post("/admin/invite/{$token}", ['name' => 'X', 'password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
        ->assertNotFound();
    expect(PlatformAdmin::query()->where('email', 'new@pawbook.test')->count())->toBe(1);
});

it('404s an expired, revoked or unknown invitation token', function () {
    [$expired] = ($this->invitation)(['expires_at' => now()->subMinute()]);
    [$revoked] = ($this->invitation)(['email' => 'other@pawbook.test', 'revoked_at' => now()]);

    $this->get("/admin/invite/{$expired}")->assertNotFound();
    $this->get("/admin/invite/{$revoked}")->assertNotFound();
    $this->get('/admin/invite/'.bin2hex(random_bytes(32)))->assertNotFound();
});

it('disables and re-enables another admin, but never oneself', function () {
    $this->actingAs($this->super, 'platform');

    $this->patch("/admin/admins/{$this->super->id}/status", ['is_active' => false])->assertSessionHasErrors('is_active');
    expect($this->super->fresh()->is_active)->toBeTrue();

    $this->patch("/admin/admins/{$this->support->id}/status", ['is_active' => false])->assertSessionHasNoErrors();
    expect($this->support->fresh()->is_active)->toBeFalse()
        ->and(PlatformAdmin::query()->whereKey($this->support->id)->exists())->toBeTrue();

    $this->patch("/admin/admins/{$this->support->id}/status", ['is_active' => true]);
    expect($this->support->fresh()->is_active)->toBeTrue();
});
