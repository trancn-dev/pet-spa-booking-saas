<?php

declare(strict_types=1);

use App\Actions\Roles\CreateDefaultRoles;
use App\Enums\TenantStatus;
use App\Mail\ShopInvitationMail;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Platform\TenantSubscription;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\ShopSlug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/*
| Task 4.3: ProvisionTenant is the only way a shop is created.
*/

it('creates the whole shop in one go and queues the owner invitation', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create(['name' => 'Nguyễn Văn A']);

    $shop = provisionShop(['ownerEmail' => 'Chu@Paw.test', 'ownerPhone' => '+84903000111'], $admin, sendInvitation: true);
    $tenant = $shop->tenant;

    $inShop = inTenant($tenant, fn () => [
        'settings' => TenantSetting::query()->count(),
        'branches' => Branch::query()->pluck('code')->all(),
        'strays' => Customer::query()->where('is_system', true)->count(),
        'drawers' => CashDrawer::query()->pluck('name')->all(),
        'invitation' => Invitation::query()->sole(),
    ]);

    expect($tenant->status)->toBe(TenantStatus::Trial)
        ->and($tenant->plan_expires_at?->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($tenant->created_by_admin_id)->toBe($admin->id)
        ->and($tenant->contact_phone)->toBe('+84903000111')
        ->and($inShop['settings'])->toBe(1)
        ->and($inShop['branches'])->toBe(['Q1'])
        ->and($inShop['strays'])->toBe(1)
        ->and($inShop['drawers'])->toBe(['Két 1'])
        ->and(Role::query()->where('team_id', $tenant->id)->count())->toBe(7)
        ->and(TenantSubscription::query()->where('tenant_id', $tenant->id)->sole()->amount)->toBe('0.00')
        ->and(PlatformAuditLog::query()->where('tenant_id', $tenant->id)->where('action', 'tenant.created')->count())->toBe(1);

    $invitation = $inShop['invitation'];
    expect($invitation->email)->toBe('chu@paw.test')
        ->and($invitation->role_names)->toBe(['owner'])
        ->and($invitation->invited_by_admin_id)->toBe($admin->id)
        ->and($invitation->token_hash)->toBe(hash('sha256', $shop->token))
        ->and($invitation->expires_at?->isSameDay(now()->addDays(7)))->toBeTrue();

    Mail::assertQueued(ShopInvitationMail::class, fn (ShopInvitationMail $mail) => $mail->hasTo('chu@paw.test')
        && $mail->url === url("/invite/{$shop->token}")
        && $mail->roleLabel === 'Chủ shop'
        && $mail->invitedBy === 'Nguyễn Văn A');
});

it('records a sale as an active shop with its paid subscription', function () {
    $shop = provisionShop([
        'trialDays' => null,
        'paidFrom' => now()->startOfDay(),
        'paidUntil' => now()->addYear()->endOfDay(),
        'amount' => '6000000',
        'paymentRef' => 'CK-123',
    ]);

    $subscription = TenantSubscription::query()->where('tenant_id', $shop->tenant->id)->sole();

    expect($shop->tenant->status)->toBe(TenantStatus::Active)
        ->and($subscription->amount)->toBe('6000000.00')
        ->and($subscription->payment_ref)->toBe('CK-123')
        ->and($subscription->paid_at)->not->toBeNull();
});

it('rolls everything back when a step fails', function () {
    Mail::fake();
    app()->instance(CreateDefaultRoles::class, new class extends CreateDefaultRoles
    {
        public function handle(Tenant $tenant): void
        {
            throw new RuntimeException('roles failed');
        }
    });

    expect(fn () => provisionShop(sendInvitation: true))->toThrow(RuntimeException::class, 'roles failed');

    foreach (['tenants', 'tenant_settings', 'tenant_subscriptions', 'branches', 'customers', 'cash_drawers', 'invitations', 'platform_audit_logs'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
    Mail::assertNothingQueued();
});

it('refuses a taken, reserved or malformed slug', function (string $slug, string $error) {
    provisionShop(['slug' => 'paw-house', 'ownerEmail' => 'first@paw.test']);

    try {
        provisionShop(['slug' => $slug, 'plan' => Plan::factory()->create()]);
        $this->fail('Expected a validation error');
    } catch (ValidationException $e) {
        expect($e->errors()['slug'][0])->toBe($error);
    }

    expect(Tenant::query()->count())->toBe(1);
})->with([
    'taken' => ['paw-house', 'Địa chỉ này đã có shop dùng.'],
    'central route' => ['admin', 'Địa chỉ này đã được hệ thống dùng, chọn tên khác.'],
    'invite page' => ['invite', 'Địa chỉ này đã được hệ thống dùng, chọn tên khác.'],
    'upper case and spaces' => ['Paw House', 'Chỉ dùng chữ thường không dấu, số và dấu gạch ngang.'],
]);

it('reserves the first segment of every central route', function () {
    $segments = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => explode('/', trim($route->uri(), '/'))[0])
        ->reject(fn (string $segment) => $segment === '' || str_starts_with($segment, '{'))
        ->unique()->values();

    expect($segments->diff(ShopSlug::RESERVED)->values()->all())->toBe([]);
});
