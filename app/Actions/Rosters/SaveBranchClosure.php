<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\AppointmentItemStatus;
use App\Models\AppointmentItem;
use App\Models\BranchClosure;
use App\Scheduling\ShopClock;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A day a branch (or, with no branch, the whole shop) is closed. UNIQUE(tenant_id, branch_id, date)
 * does not stop two whole-shop rows (NULL branch), so duplicates are checked here, under a lock on
 * the shop's closures of that day. Returns how many booked lines fall on that day.
 */
class SaveBranchClosure
{
    /**
     * @return array{0: BranchClosure, 1: int}
     */
    public function handle(?int $branchId, string $date, ?string $reason): array
    {
        return DB::transaction(function () use ($branchId, $date, $reason): array {
            $sameDay = BranchClosure::query()->where('date', $date)->lockForUpdate()->get();
            if ($sameDay->contains(fn (BranchClosure $c) => $c->branch_id === null || $c->branch_id === $branchId)) {
                throw ValidationException::withMessages(['date' => 'Ngày này đã được đánh dấu nghỉ.']);
            }

            $closure = BranchClosure::query()->create([
                'branch_id' => $branchId,
                'date' => $date,
                'reason' => $reason === null || trim($reason) === '' ? null : trim($reason),
            ]);
            ActivityLog::record('branch.closed', $closure, ['date' => $date], branchId: $branchId);

            [$from, $to] = ShopClock::current()->localDayRangeUtc($date);
            $affected = AppointmentItem::query()
                ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
                ->where('status', AppointmentItemStatus::Booked->value)
                ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)->count();

            return [$closure, $affected];
        });
    }
}
