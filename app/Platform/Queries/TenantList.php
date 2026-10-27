<?php

declare(strict_types=1);

namespace App\Platform\Queries;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A2: every shop with aggregate numbers only (never customers, pets, invoices).
 * Counts come from correlated subqueries: a fixed number of queries whatever the page size.
 */
class TenantList
{
    public const EXPIRING_DAYS = 7;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(?string $search, ?TenantStatus $status, int $perPage = 20): LengthAwarePaginator
    {
        return $this->context->withoutTenancy(fn (): LengthAwarePaginator => Tenant::query()
            ->select('tenants.*')
            ->addSelect([
                'branches_count' => DB::table('branches')->selectRaw('COUNT(*)')
                    ->whereColumn('branches.tenant_id', 'tenants.id')->whereNull('branches.deleted_at'),
                'staff_count' => DB::table('staff')->selectRaw('COUNT(*)')
                    ->whereColumn('staff.tenant_id', 'tenants.id')->whereNull('staff.deleted_at')->where('staff.status', 'active'),
                // The plan-quota counter: appointments created this month (online + staff).
                'appointments_this_month' => DB::table('tenant_usage')->select('appointments_count')
                    ->whereColumn('tenant_usage.tenant_id', 'tenants.id')->where('tenant_usage.period', now()->format('Y-m'))->limit(1),
            ])
            ->with('plan:id,code,name')
            ->when($search !== null && $search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $like = '%'.addcslashes((string) $search, '%_\\').'%';
                $query->where('tenants.name', 'like', $like)
                    ->orWhere('tenants.slug', 'like', $like)
                    ->orWhere('tenants.contact_name', 'like', $like)
                    ->orWhere('tenants.contact_phone', 'like', $like);
            }))
            ->when($status !== null, fn (Builder $query) => $query->where('tenants.status', $status))
            ->orderBy('tenants.name')
            ->paginate($perPage)
            ->withQueryString());
    }

    /**
     * @return array{total: int, active: int, expiring: int, overdue: int}
     */
    public function kpis(): array
    {
        $row = DB::table('tenants')->selectRaw(
            "COUNT(*) AS total,
             SUM(status IN ('trial', 'active')) AS active,
             SUM(status IN ('trial', 'active') AND plan_expires_at BETWEEN ? AND ?) AS expiring,
             SUM(status IN ('past_due', 'suspended')) AS overdue",
            [now(), now()->addDays(self::EXPIRING_DAYS)],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'expiring' => (int) ($row->expiring ?? 0),
            'overdue' => (int) ($row->overdue ?? 0),
        ];
    }
}
