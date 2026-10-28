<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\AppointmentItemStatus;
use App\Models\AppointmentItem;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Scheduling\ShopClock;
use App\Support\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Time off for one person, typed in shop time and stored in UTC. Appointments already assigned to
 * them in that period are reported, never cancelled: the front desk decides (S8).
 */
class AddTimeOff
{
    /**
     * @param  array{starts_on: string, starts_time: string|null, ends_on: string, ends_time: string|null, reason: string|null}  $data
     * @return array{0: StaffTimeOff, 1: Collection<int, AppointmentItem>}
     */
    public function handle(Staff $staff, array $data, ?Staff $by = null): array
    {
        $clock = ShopClock::current();
        // A day without a time = the whole day.
        $start = $clock->toUtc($data['starts_on'], $data['starts_time'] ?? '00:00');
        $end = $data['ends_time'] !== null
            ? $clock->toUtc($data['ends_on'], $data['ends_time'])
            : $clock->localDayRangeUtc($data['ends_on'])[1];

        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ends_on' => 'Thời điểm kết thúc phải sau lúc bắt đầu.']);
        }

        $off = StaffTimeOff::query()->create([
            'staff_id' => $staff->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'reason' => $data['reason'] === null || trim($data['reason']) === '' ? null : trim($data['reason']),
            'created_by' => $by?->id,
        ]);
        ActivityLog::record('roster.time_off_added', $off, ['staff' => $staff->display_name]);

        return [$off, self::affected($staff->id, $start, $end)];
    }

    /**
     * Booked appointment lines assigned to the person that overlap the period.
     *
     * @return Collection<int, AppointmentItem>
     */
    public static function affected(int $staffId, CarbonImmutable|\DateTimeInterface $start, CarbonImmutable|\DateTimeInterface $end): Collection
    {
        return AppointmentItem::query()->where('assigned_staff_id', $staffId)
            ->where('status', AppointmentItemStatus::Booked->value)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->orderBy('starts_at')->get();
    }
}
