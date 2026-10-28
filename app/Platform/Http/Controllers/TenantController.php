<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Platform\ImpersonationSession;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAuditLog;
use App\Models\Platform\TenantSubscription;
use App\Models\Tenant;
use App\Platform\Actions\ExtendSubscription;
use App\Platform\Actions\ProvisionTenant;
use App\Platform\Actions\ResendOwnerInvitation;
use App\Platform\Actions\ResumeTenant;
use App\Platform\Actions\SuspendTenant;
use App\Platform\Http\Requests\StoreTenantRequest;
use App\Platform\Impersonation;
use App\Platform\Queries\TenantList;
use App\Platform\Queries\TenantOverview;
use App\Support\ShopSlug;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A2 shop list, A3 create (super-admin), A4 shop detail with billing and log tabs, extend /
 * suspend / resume (super-admin; support: read only).
 */
class TenantController extends Controller
{
    public function index(Request $request, TenantList $list): Response
    {
        $search = $request->string('q')->trim()->value();
        $status = TenantStatus::tryFrom($request->string('status')->value());
        $admin = $request->user('platform');

        $page = $list->paginate($search, $status);

        return Inertia::render('Admin/Tenants/Index', [
            'kpis' => $list->kpis(),
            'tenants' => $page->through(fn (Tenant $tenant): array => [
                'id' => $tenant->public_id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'owner_name' => $tenant->contact_name,
                'owner_phone' => $tenant->contact_phone,
                'plan' => $tenant->plan?->name,
                'plan_expires_at' => $tenant->plan_expires_at?->toIso8601String(),
                'status' => $tenant->status->value,
                'branches_count' => (int) $tenant->getAttribute('branches_count'),
                'staff_count' => (int) $tenant->getAttribute('staff_count'),
                'appointments_this_month' => (int) $tenant->getAttribute('appointments_this_month'),
            ]),
            'filters' => ['q' => $search, 'status' => $status?->value],
            'expiringDays' => TenantList::EXPIRING_DAYS,
            'can' => [
                'createTenant' => $admin instanceof PlatformAdmin && $admin->isSuperAdmin(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tenants/Create', [
            'plans' => Plan::query()->orderBy('id')->get(['id', 'code', 'name', 'max_branches', 'max_staff', 'max_appointments_per_month']),
            'timezones' => DateTimeZone::listIdentifiers(DateTimeZone::ASIA),
            'defaults' => ['timezone' => 'Asia/Ho_Chi_Minh', 'trial_days' => (int) config('platform.trial_days')],
            'siteHost' => config('platform.site_host'),
        ]);
    }

    /**
     * A3 realtime slug check.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $validator = Validator::make(['slug' => $request->string('slug')->value()], ['slug' => ShopSlug::rules()], ShopSlug::messages());

        return response()->json([
            'available' => ! $validator->fails(),
            'message' => $validator->errors()->first('slug') ?: null,
        ]);
    }

    public function store(StoreTenantRequest $request, ProvisionTenant $provision): RedirectResponse
    {
        $admin = $request->user('platform');
        assert($admin instanceof PlatformAdmin);

        $shop = $provision->handle($admin, $request->toNewShop());

        return to_route('admin.tenants.show', $shop->tenant->public_id)
            ->with('status', 'Đã tạo shop và gửi lời mời tới '.$shop->invitation->email.'.')
            ->with('invite_url', $shop->url());
    }

    public function show(Request $request, Tenant $tenant, TenantOverview $overview): Response
    {
        $admin = $request->user('platform');
        $tab = in_array($request->string('tab')->value(), ['billing', 'support', 'logs'], true) ? $request->string('tab')->value() : 'overview';

        return Inertia::render('Admin/Tenants/Show', [
            'tab' => $tab,
            'tenant' => $overview->for($tenant),
            // The raw link exists only right after creating / resending (the DB keeps a hash).
            'inviteUrl' => $request->session()->get('invite_url'),
            'subscriptions' => fn () => $tab !== 'billing' ? [] : TenantSubscription::query()
                ->where('tenant_id', $tenant->getKey())->with(['plan:id,name', 'createdBy:id,name'])->latest('starts_at')->get()
                ->map(fn (TenantSubscription $s): array => [
                    'id' => $s->id,
                    'plan' => $s->plan?->name,
                    'starts_at' => $s->starts_at->toIso8601String(),
                    'ends_at' => $s->ends_at->toIso8601String(),
                    'amount' => $s->amount,
                    'paid_at' => $s->paid_at?->toIso8601String(),
                    'payment_ref' => $s->payment_ref,
                    'note' => $s->note,
                    'by' => $s->createdBy?->name,
                ])->values(),
            'plans' => fn () => $tab !== 'billing' ? [] : Plan::query()->orderBy('id')->get(['id', 'code', 'name']),
            'sessions' => $tab !== 'support' ? [] : self::sessions($tenant),
            'csrf' => csrf_token(),
            'logs' => $tab === 'logs' ? AuditLogController::rows(PlatformAuditLog::query()->where('tenant_id', $tenant->getKey())) : null,
            'can' => ['manage' => $admin instanceof PlatformAdmin && $admin->isSuperAdmin()],
        ]);
    }

    public function extend(Request $request, Tenant $tenant, ExtendSubscription $extend): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $extend->handle(
            $this->admin($request),
            $tenant,
            Plan::query()->findOrFail((int) $data['plan_id']),
            (int) $data['months'],
            (string) $data['amount'],
            $data['payment_ref'] ?? null,
            $data['note'] ?? null,
        );

        return to_route('admin.tenants.show', [$tenant->public_id, 'tab' => 'billing'])->with('status', 'Đã gia hạn '.$tenant->name.'.');
    }

    public function suspend(Request $request, Tenant $tenant, SuspendTenant $suspend): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Nhập lý do tạm khóa.']);
        $suspend->handle($this->admin($request), $tenant, (string) $data['reason']);

        return back()->with('status', 'Đã tạm khóa '.$tenant->name.'.');
    }

    public function resume(Request $request, Tenant $tenant, ResumeTenant $resume): RedirectResponse
    {
        $resume->handle($this->admin($request), $tenant);

        return back()->with('status', 'Đã mở khóa '.$tenant->name.'.');
    }

    /**
     * A4 "Đăng nhập như chủ shop": a plain form posted to a new tab; this answers with the one-time
     * CMS link, so the token never sits in the admin page.
     */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Nhập lý do đăng nhập thay.']);
        [, $token] = Impersonation::start($this->admin($request), $tenant, (string) $data['reason'], $request);

        return redirect()->away(route('impersonation.consume', ['slug' => $tenant->slug, 'token' => $token]))
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Support access history of one shop (A4 tab, and the owner's own view in the CMS).
     *
     * @return list<array<string, mixed>>
     */
    public static function sessions(Tenant $tenant): array
    {
        return ImpersonationSession::query()->where('tenant_id', $tenant->getKey())
            ->with(['admin:id,name', 'targetUser:id,name,email'])->latest('id')->limit(100)->get()
            ->map(fn (ImpersonationSession $s): array => [
                'id' => $s->id,
                'admin' => $s->admin?->name,
                'target' => $s->targetUser?->name,
                'reason' => $s->reason,
                'started_at' => $s->started_at?->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'expires_at' => $s->expires_at->toIso8601String(),
                'ip' => $s->ip,
                'state' => match (true) {
                    $s->started_at === null => 'unused',
                    $s->ended_at !== null => 'ended',
                    $s->expires_at->isPast() => 'expired',
                    default => 'open',
                },
            ])->values()->all();
    }

    private function admin(Request $request): PlatformAdmin
    {
        $admin = $request->user('platform');
        assert($admin instanceof PlatformAdmin);

        return $admin;
    }

    public function resendInvitation(Request $request, Tenant $tenant, ResendOwnerInvitation $resend): RedirectResponse
    {
        $admin = $request->user('platform');
        assert($admin instanceof PlatformAdmin);

        $mail = ! $request->boolean('link_only');
        $url = $resend->handle($admin, $tenant, $mail);

        return back()
            ->with('status', $mail ? 'Đã gửi lại lời mời. Link cũ không còn dùng được.' : 'Đã tạo link mời mới. Link cũ không còn dùng được.')
            ->with('invite_url', $url);
    }
}
