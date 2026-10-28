<?php

declare(strict_types=1);

namespace App\Actions\Visits;

use App\Enums\ServiceGroup;
use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Scheduling\ShopClock;
use App\Support\DailyCounter;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Opens a Visit in the branch queue (db-schema 2.6): shop-local visit day, a queue number of the
 * day that never changes, waiting. The queue is ordered by is_emergency, then priority_at.
 * Task 5.6 uses it for check-in; task 5.8 adds walk-ins and emergencies on top.
 */
class CreateVisit
{
    public function handle(
        int $branchId,
        ?int $customerId,
        ?int $petId,
        ServiceGroup $group,
        CarbonImmutable $priorityAt,
        ?int $assignedStaffId = null,
        ?string $reason = null,
        ?int $createdBy = null,
        bool $isEmergency = false,
        ?string $emergencyLabel = null,
    ): Visit {
        $clock = ShopClock::current();
        $now = CarbonImmutable::now();
        $day = $clock->today($now)->format('Y-m-d');

        return Visit::query()->create([
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'pet_id' => $petId,
            'service_group' => $group,
            'visit_date' => $day,
            'queue_no' => DailyCounter::next((int) app(TenantContext::class)->require()->getKey(), $branchId, $day, 'queue'),
            'status' => VisitStatus::Waiting,
            'is_emergency' => $isEmergency,
            'emergency_label' => $emergencyLabel,
            'priority_at' => $priorityAt,
            'assigned_staff_id' => $assignedStaffId,
            'reason' => $reason,
            'checked_in_at' => $now,
            'created_by' => $createdBy,
        ]);
    }
}
