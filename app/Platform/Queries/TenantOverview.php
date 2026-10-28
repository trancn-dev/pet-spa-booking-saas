<?php

declare(strict_types=1);

namespace App\Platform\Queries;

use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * A4 tab "Tổng quan": shop info, owner invitation, quota usage. Aggregates only, never customers,
 * pets or invoices (db-schema 2.1).
 */
class TenantOverview
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Tenant $tenant): array
    {
        $tenant->loadMissing('plan');
        $plan = $tenant->plan;

        [$invitation, $branches, $staff] = $this->context->runAs($tenant, fn (): array => [
            Invitation::query()->whereJsonContains('role_names', 'owner')->latest('id')->first(),
            Branch::query()->count(),
            // Quota counts people with an account (db-schema 2.2); people without one are free.
            Staff::query()->whereNotNull('user_id')->where('status', 'active')->count(),
        ]);

        $appointments = (int) DB::table('tenant_usage')->where('tenant_id', $tenant->getKey())
            ->where('period', now($tenant->timezone)->format('Y-m'))->value('appointments_count');

        return [
            'id' => $tenant->public_id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status->value,
            'suspended_at' => $tenant->suspended_at?->toIso8601String(),
            'suspended_reason' => $tenant->suspended_reason,
            'timezone' => $tenant->timezone,
            'plan' => $plan?->name,
            'plan_expires_at' => $tenant->plan_expires_at?->toIso8601String(),
            'contact_name' => $tenant->contact_name,
            'contact_phone' => $tenant->contact_phone,
            'admin_note' => $tenant->admin_note,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'site_url' => 'https://'.config('platform.site_host').'/'.$tenant->slug,
            'invitation' => $invitation === null ? null : [
                'email' => $invitation->email,
                'state' => $invitation->state()->value,
                'state_label' => $invitation->state()->label(),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'accepted_at' => $invitation->accepted_at?->toIso8601String(),
            ],
            'usage' => [
                ['label' => 'Cơ sở', 'used' => $branches, 'max' => $plan?->max_branches],
                ['label' => 'Nhân viên có tài khoản', 'used' => $staff, 'max' => $plan?->max_staff],
                ['label' => 'Lịch hẹn tháng này', 'used' => $appointments, 'max' => $plan?->max_appointments_per_month],
            ],
        ];
    }
}
