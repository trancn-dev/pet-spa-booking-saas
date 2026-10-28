<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\ServiceGroup;
use App\Enums\StaffStatus;
use App\Models\BranchClosure;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use Carbon\CarbonImmutable;

/**
 * Who works where and when, for a range of shop-local days (S8, capacity in task 5.4).
 *
 * A slot is a weekly rule (`weekday`, local TIME, effective range); each day turns it into a UTC
 * interval with ShopClock. A slot counts for its own `service_group` only, so a person with both
 * the vet and the groomer role is one person on duty, never two. A slot fully covered by time off
 * does not count; a closed branch (or shop) day has nobody on duty.
 */
final class Roster implements DutySource
{
    public function __construct(private readonly ShopClock $clock) {}

    /** @var array<string, array{0: list<array{date: string, slot: StaffWorkingHour, starts_at: CarbonImmutable, ends_at: CarbonImmutable, off: bool}>, 1: array<int, list<StaffTimeOff>>}> */
    private array $loaded = [];

    /**
     * Slots happening on each day, with their UTC interval. `off` = fully covered by time off.
     *
     * @return list<array{date: string, slot: StaffWorkingHour, starts_at: CarbonImmutable, ends_at: CarbonImmutable, off: bool}>
     */
    public function occurrences(int $branchId, string $from, string $to): array
    {
        return $this->load($branchId, $from, $to)[0];
    }

    /**
     * Time on duty per person for one group: their slots of that group minus any time off (even
     * part of a slot), closed days removed. Three queries for the whole range.
     *
     * @return array<int, IntervalSet> staff id => time on duty
     */
    public function dutySets(int $branchId, ServiceGroup $group, string $from, string $to): array
    {
        [$occurrences, $timeOff] = $this->load($branchId, $from, $to);

        $pieces = [];
        foreach ($occurrences as $o) {
            if ($o['slot']->service_group === $group) {
                $pieces[$o['slot']->staff_id][] = Interval::of($o['starts_at'], $o['ends_at']);
            }
        }

        $sets = [];
        foreach ($pieces as $staffId => $intervals) {
            $off = new IntervalSet(array_map(fn (StaffTimeOff $t) => Interval::of($t->starts_at, $t->ends_at), $timeOff[$staffId] ?? []));
            $sets[$staffId] = (new IntervalSet($intervals))->subtract($off);
        }

        return $sets;
    }

    /**
     * @return array{0: list<array{date: string, slot: StaffWorkingHour, starts_at: CarbonImmutable, ends_at: CarbonImmutable, off: bool}>, 1: array<int, list<StaffTimeOff>>}
     */
    private function load(int $branchId, string $from, string $to): array
    {
        $key = "{$branchId}:{$from}:{$to}";
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }

        $days = $this->days($from, $to);
        $slots = StaffWorkingHour::query()->where('branch_id', $branchId)
            ->whereHas('staff', fn ($q) => $q->where('status', StaffStatus::Active->value))
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $to))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->orderBy('start_time')->get();

        $closed = $this->closedDays($branchId, $from, $to);
        [$rangeStart] = $this->clock->localDayRangeUtc($from);
        [, $rangeEnd] = $this->clock->localDayRangeUtc($to);
        $timeOff = StaffTimeOff::query()->whereIn('staff_id', $slots->pluck('staff_id')->unique())
            ->where('starts_at', '<', $rangeEnd)->where('ends_at', '>', $rangeStart)->orderBy('starts_at')->get()
            ->groupBy('staff_id')->map(fn ($rows) => $rows->values()->all())->all();

        $out = [];
        foreach ($days as $date) {
            if (isset($closed[$date])) {
                continue;
            }
            $weekday = $this->clock->weekday($date);
            foreach ($slots as $slot) {
                if ($slot->weekday !== $weekday
                    || ($slot->effective_from !== null && $slot->effective_from->format('Y-m-d') > $date)
                    || ($slot->effective_to !== null && $slot->effective_to->format('Y-m-d') < $date)) {
                    continue;
                }
                $start = $this->clock->toUtc($date, $slot->start_time);
                $end = $this->clock->toUtc($date, $slot->end_time);
                $off = collect($timeOff[$slot->staff_id] ?? [])
                    ->contains(fn (StaffTimeOff $t) => $t->starts_at->lte($start) && $t->ends_at->gte($end));

                $out[] = ['date' => $date, 'slot' => $slot, 'starts_at' => $start, 'ends_at' => $end, 'off' => $off];
            }
        }

        return $this->loaded[$key] = [$out, $timeOff];
    }

    /**
     * People on duty per day and group: distinct staff with at least one counted slot.
     *
     * @return array<string, array{medical: int, grooming: int}>
     */
    public function onDutyPerDay(int $branchId, string $from, string $to): array
    {
        $counts = [];
        foreach ($this->days($from, $to) as $date) {
            $counts[$date] = ['medical' => [], 'grooming' => []];
        }

        foreach ($this->occurrences($branchId, $from, $to) as $o) {
            $group = $o['slot']->service_group;
            if ($group !== null && ! $o['off']) {
                $counts[$o['date']][$group->value][$o['slot']->staff_id] = true;
            }
        }

        return array_map(fn (array $day): array => ['medical' => count($day['medical']), 'grooming' => count($day['grooming'])], $counts);
    }

    /**
     * People on duty in a group at one instant (task 5.4 builds slots on this).
     */
    public function onDutyAt(int $branchId, ServiceGroup $group, CarbonImmutable $instant): int
    {
        $date = $this->clock->toLocal($instant)->format('Y-m-d');
        $staff = [];
        foreach ($this->occurrences($branchId, $date, $date) as $o) {
            if ($o['slot']->service_group === $group && ! $o['off'] && $o['starts_at']->lte($instant) && $o['ends_at']->gt($instant)) {
                $staff[$o['slot']->staff_id] = true;
            }
        }

        return count($staff);
    }

    /**
     * @return array<string, bool>
     */
    private function closedDays(int $branchId, string $from, string $to): array
    {
        return BranchClosure::query()
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->whereBetween('date', [$from, $to])->pluck('date')
            ->mapWithKeys(fn ($d): array => [CarbonImmutable::parse($d)->format('Y-m-d') => true])->all();
    }

    /**
     * @return list<string>
     */
    private function days(string $from, string $to): array
    {
        $days = [];
        for ($d = CarbonImmutable::parse($from); $d->format('Y-m-d') <= $to; $d = $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }

        return $days;
    }
}
