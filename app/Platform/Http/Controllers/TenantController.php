<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\Actions\ProvisionTenant;
use App\Platform\Actions\ResendOwnerInvitation;
use App\Platform\Http\Requests\StoreTenantRequest;
use App\Platform\Queries\TenantList;
use App\Platform\Queries\TenantOverview;
use App\Support\ShopSlug;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A2 shop list, A3 create (super-admin), A4 shop detail (support: read only).
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

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $overview->for($tenant),
            // The raw link exists only right after creating / resending (the DB keeps a hash).
            'inviteUrl' => $request->session()->get('invite_url'),
            'can' => ['manage' => $admin instanceof PlatformAdmin && $admin->isSuperAdmin()],
        ]);
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
