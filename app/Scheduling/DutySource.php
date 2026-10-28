<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\ServiceGroup;

/**
 * Where "who is on duty" comes from. MVP: weekly rosters (`Roster`). Phase 3: days with
 * `shift_assignments` use the assigned shifts instead (db-schema 5.1 step 4); plug that in here.
 */
interface DutySource
{
    /**
     * Time on duty per person for one group at a branch, over shop-local days [from, to].
     *
     * @return array<int, IntervalSet> staff id => time on duty (UTC)
     */
    public function dutySets(int $branchId, ServiceGroup $group, string $from, string $to): array;
}
