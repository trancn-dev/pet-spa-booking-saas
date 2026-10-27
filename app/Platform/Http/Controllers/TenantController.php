<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\Queries\TenantList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A2: shop list (read-only until task 4.3 adds A3 / A4).
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
}
