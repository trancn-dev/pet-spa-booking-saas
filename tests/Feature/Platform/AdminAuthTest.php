<?php

declare(strict_types=1);

use App\Models\Platform\PlatformAdmin;
use App\Models\User;
use App\Platform\TwoFactor;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

/*
| MANDATORY (ADR 0008): the admin area is reachable only by active platform admins who passed
| password + TOTP, with a session cookie of its own.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->secret = app(Google2FA::class)->generateSecretKey(32);
    $this->otp = fn (): string => app(Google2FA::class)->getCurrentOtp($this->secret);
    $this->admin = PlatformAdmin::factory()->create([
        'email' => 'root@pawbook.test',
        'two_factor_secret' => $this->secret,
        'two_factor_confirmed_at' => now(),
    ]);
});

it('hides every protected admin page from guests and CMS users (404)', function () {
    foreach (['/admin/tenants', '/admin/admins'] as $url) {
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    }

    $this->post('/admin/logout')->assertNotFound();
});

it('sends /admin to the admin login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Login'));
});

it('signs in with password then TOTP, and only after the TOTP step', function () {
    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'password'])
        ->assertRedirect('/admin/two-factor/challenge');

    $this->assertGuest('platform');
    $this->get('/admin/tenants')->assertNotFound();

    $this->post('/admin/two-factor/challenge', ['code' => ($this->otp)()])->assertRedirect('/admin/tenants');

    $this->assertAuthenticatedAs($this->admin, 'platform');
    $this->get('/admin/tenants')->assertOk();
    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong or reused TOTP code', function () {
    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'password']);
    $this->post('/admin/two-factor/challenge', ['code' => '000000'])->assertSessionHasErrors('code');

    $code = ($this->otp)();
    $this->post('/admin/two-factor/challenge', ['code' => $code]);
    $this->post('/admin/logout');

    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'password']);
    $this->post('/admin/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest('platform');
});

it('gives one generic error for a wrong password and for a disabled admin', function () {
    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'nope'])
        ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);

    $this->admin->forceFill(['is_active' => false])->save();
    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
});

it('locks the login after 5 failed attempts per email + IP', function () {
    foreach (range(1, 5) as $_) {
        $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'nope']);
    }

    $this->post('/admin/login', ['email' => 'root@pawbook.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Quá nhiều lần thử');
});

it('forces an admin without 2FA through setup before reaching A2', function () {
    $new = PlatformAdmin::factory()->create(['email' => 'new@pawbook.test']);

    $this->post('/admin/login', ['email' => 'new@pawbook.test', 'password' => 'password'])
        ->assertRedirect('/admin/two-factor/setup');
    $this->get('/admin/tenants')->assertNotFound();

    $this->get('/admin/two-factor/setup')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/TwoFactor/Setup')->has('qrSvg')->has('secret'));

    $secret = (string) $new->fresh()->two_factor_secret;
    $this->post('/admin/two-factor/setup', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
        ->assertRedirect('/admin/tenants');

    expect($new->fresh()->two_factor_confirmed_at)->not->toBeNull();
    $this->assertAuthenticatedAs($new, 'platform');
});

it('lets demo admins skip 2FA only in local / demo, never in production', function () {
    config(['platform.skip_two_factor' => true]);
    PlatformAdmin::factory()->create(['email' => 'demo@pawbook.test']);

    // Outside `testing` the CSRF check is live again; it is not what this test is about.
    $this->withoutMiddleware(ValidateCsrfToken::class);

    app()->detectEnvironment(fn () => 'production');
    expect(TwoFactor::canSkip())->toBeFalse();
    $this->post('/admin/login', ['email' => 'demo@pawbook.test', 'password' => 'password'])
        ->assertRedirect('/admin/two-factor/setup');
    $this->assertGuest('platform');

    app()->detectEnvironment(fn () => 'local');
    expect(TwoFactor::canSkip())->toBeTrue();
    $this->post('/admin/login', ['email' => 'demo@pawbook.test', 'password' => 'password'])
        ->assertRedirect('/admin/tenants');
    $this->assertAuthenticated('platform');

    app()->detectEnvironment(fn () => 'testing');
});

it('uses its own session cookie, separate from the CMS one', function () {
    $admin = $this->get('/admin/login');
    $cms = $this->get('/login');

    $admin->assertCookie('pawbook_admin_session')->assertCookieMissing(config('session.cookie'));
    $cms->assertCookie(config('session.cookie'))->assertCookieMissing('pawbook_admin_session');
});

it('keeps the admin signed in when the CMS user signs out', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin, 'platform')->actingAs($user, 'web');
    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest('web');
    $this->get('/admin/tenants')->assertOk();
});

it('signs out an admin who gets disabled mid-session', function () {
    $this->actingAs($this->admin, 'platform')->get('/admin/tenants')->assertOk();

    $this->admin->forceFill(['is_active' => false])->save();
    $this->get('/admin/tenants')->assertNotFound();
    $this->assertGuest('platform');
});
