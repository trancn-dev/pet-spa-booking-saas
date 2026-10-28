<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\AppointmentItemStatus;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\ServiceGroup;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Pricing\ServiceQuoteResolver;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Free start times by capacity (db-schema 5.1, task 5.4), per branch and group, on a 15-minute grid
 * of the shop's local day.
 *
 * - On duty in a cell = people whose duty time (roster slots of that group, minus time off and
 *   closed days) covers the whole cell, and only inside opening hours (a closed day has none).
 * - Online capacity of a cell = floor(on duty × ratio of that weekday / hour).
 * - Taken = booked / checked-in lines of live appointments overlapping the cell. The Site compares
 *   only online lines with the online capacity; the front desk sees every line against the
 *   people on duty, as a warning.
 *
 * The whole range loads in a fixed number of queries (rosters, time off, closures, rates, lines,
 * one quote per planned line); everything else is integer arithmetic on cells.
 */
class AvailabilityService
{
    public const CELL = 900; // 15 minutes, in seconds

    public const MAX_DAYS = 31;

    public function __construct(
        private readonly ShopClock $clock,
        private readonly DutySource $duty,
        private readonly ServiceQuoteResolver $quotes,
        private readonly OnlineRates $rates = new OnlineRates,
    ) {}

    /**
     * Start times the Site can offer: every group of the booking keeps enough online seats in
     * every cell it covers (buffers included), from now + lead time.
     *
     * @param  list<BookingLine>  $lines
     * @return array<string, list<array{time: string, starts_at: string}>> local date => free starts
     */
    public function slotsForOnline(Branch $branch, array $lines, string $from, string $to, ?CarbonImmutable $now = null): array
    {
        $plan = $this->plan($lines);
        $grid = $this->grid($branch, array_keys($plan['groups']), $from, $to);
        $earliest = ($now ?? CarbonImmutable::now())->getTimestamp() + 60 * (int) config('booking.online_lead_minutes', 60);

        $out = [];
        foreach ($grid['days'] as $date => $day) {
            $out[$date] = [];
            foreach ($this->starts($day, $plan['span']) as $k) {
                $start = $day['start'] + $k * self::CELL;
                if ($start < $earliest) {
                    continue;
                }
                if ($this->fitsOnline($grid, $date, $k, $plan)) {
                    $out[$date][] = $this->describe($start);
                }
            }
        }

        return $out;
    }

    /**
     * Every start inside opening hours for the front desk, with what each group looks like over the
     * booking (fewest people on duty, most lines taken) and whether it goes over the people on duty.
     * Nothing is refused here: the front desk may overbook knowingly.
     *
     * @param  list<BookingLine>  $lines  may be empty: plain occupancy of each cell
     * @return array<string, list<array{time: string, starts_at: string, over: bool, groups: array<string, array{on_duty: int, taken: int, online_taken: int, online_capacity: int}>}>>
     */
    public function slotsForStaff(Branch $branch, array $lines, string $from, string $to): array
    {
        $plan = $this->plan($lines);
        $groups = $plan['groups'] === [] ? ServiceGroup::cases() : array_map(fn (string $g) => ServiceGroup::from($g), array_keys($plan['groups']));
        $grid = $this->grid($branch, array_map(fn (ServiceGroup $g) => $g->value, $groups), $from, $to);

        $out = [];
        foreach ($grid['days'] as $date => $day) {
            $out[$date] = [];
            foreach ($this->starts($day, max($plan['span'], 1)) as $k) {
                $over = false;
                $summary = [];
                foreach ($groups as $group) {
                    $g = $group->value;
                    $demand = $plan['groups'][$g] ?? [0];
                    $onDuty = PHP_INT_MAX;
                    $taken = $online = 0;
                    $capacity = PHP_INT_MAX;
                    foreach ($demand as $j => $need) {
                        $cell = $k + $j;
                        $onDuty = min($onDuty, $grid['duty'][$g][$date][$cell] ?? 0);
                        $capacity = min($capacity, $grid['capacity'][$g][$date][$cell] ?? 0);
                        $taken = max($taken, $grid['taken'][$g][$date][$cell] ?? 0);
                        $online = max($online, $grid['online'][$g][$date][$cell] ?? 0);
                        if (($grid['taken'][$g][$date][$cell] ?? 0) + $need > ($grid['duty'][$g][$date][$cell] ?? 0)) {
                            $over = true;
                        }
                    }
                    $summary[$g] = ['on_duty' => $onDuty, 'taken' => $taken, 'online_taken' => $online, 'online_capacity' => $capacity];
                }
                $out[$date][] = [...$this->describe($day['start'] + $k * self::CELL), 'over' => $over, 'groups' => $summary];
            }
        }

        return $out;
    }

    /**
     * Seats each cell of a booking needs, per group. Every line starts at the booking's start
     * (db-schema 5.1 / task 5.5) and lasts its duration plus buffer; a partly covered cell counts.
     *
     * @param  list<BookingLine>  $lines
     * @return array{groups: array<string, array<int, int>>, span: int} span in cells
     */
    public function plan(array $lines): array
    {
        return self::demand(array_map(function (BookingLine $line): array {
            $quote = $this->quotes->resolve($line->service, $line->pet);

            return [$line->service->service_group, $quote->blockedMin() * 60];
        }, $lines));
    }

    /**
     * @param  list<array{0: ServiceGroup, 1: int}>  $lines  group and length in seconds
     * @return array{groups: array<string, array<int, int>>, span: int}
     */
    public static function demand(array $lines): array
    {
        $groups = [];
        $span = 0;
        foreach ($lines as [$group, $length]) {
            $cells = max(1, intdiv($length + self::CELL - 1, self::CELL));
            for ($j = 0; $j < $cells; $j++) {
                $groups[$group->value][$j] = ($groups[$group->value][$j] ?? 0) + 1;
            }
            $span = max($span, $cells);
        }
        ksort($groups);

        return ['groups' => $groups, 'span' => $span];
    }

    /**
     * One start, checked for a booking being saved (task 5.5, under the capacity locks): whether
     * it is inside opening hours, fits the online capacity, goes over the people on duty, or has
     * nobody on duty, per group.
     *
     * @param  array{groups: array<string, array<int, int>>, span: int}  $plan
     * @return array{open: bool, groups: array<string, array{fits_online: bool, over_duty: bool, no_duty: bool}>}
     */
    public function assess(Branch $branch, array $plan, CarbonImmutable $start): array
    {
        $date = $this->clock->toLocal($start)->format('Y-m-d');
        $grid = $this->grid($branch, array_keys($plan['groups']), $date, $date);
        $day = $grid['days'][$date];
        $offset = $start->getTimestamp() - $day['start'];
        $k = intdiv($offset, self::CELL);

        $open = $offset % self::CELL === 0 && $k >= 0 && in_array($k, $this->starts($day, $plan['span']), true);

        $groups = [];
        foreach ($plan['groups'] as $g => $demand) {
            $fits = true;
            $over = false;
            $none = false;
            foreach ($demand as $j => $need) {
                $cell = $k + $j;
                $duty = $grid['duty'][$g][$date][$cell] ?? 0;
                $fits = $fits && ($grid['online'][$g][$date][$cell] ?? 0) + $need <= ($grid['capacity'][$g][$date][$cell] ?? 0);
                $over = $over || ($grid['taken'][$g][$date][$cell] ?? 0) + $need > $duty;
                $none = $none || $duty === 0;
            }
            $groups[$g] = ['fits_online' => $fits, 'over_duty' => $over, 'no_duty' => $none];
        }

        return ['open' => $open, 'groups' => $groups];
    }

    /**
     * Per local day: its cells, opening hours, and per group the people on duty, online capacity,
     * all lines taken and online lines taken in each cell.
     *
     * @param  list<string>  $groups
     * @return array{
     *     days: array<string, array{start: int, cells: int, open: array<int, bool>}>,
     *     duty: array<string, array<string, array<int, int>>>,
     *     capacity: array<string, array<string, array<int, int>>>,
     *     taken: array<string, array<string, array<int, int>>>,
     *     online: array<string, array<string, array<int, int>>>
     * }
     */
    private function grid(Branch $branch, array $groups, string $from, string $to): array
    {
        $dates = $this->dates($from, $to);
        $closed = BranchClosure::query()->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereBetween('date', [$from, $to])->get()->mapWithKeys(fn (BranchClosure $c) => [$c->date->format('Y-m-d') => true]);
        $days = [];
        foreach ($dates as $date) {
            [$start, $end] = $this->clock->localDayRangeUtc($date);
            $cells = intdiv($end->getTimestamp() - $start->getTimestamp(), self::CELL);
            $open = $closed->has($date) ? array_fill(0, $cells, false) : $this->openCells($branch, $date, $start->getTimestamp(), $cells);
            $days[$date] = ['start' => $start->getTimestamp(), 'cells' => $cells, 'open' => $open];
        }

        $grid = ['days' => $days, 'duty' => [], 'capacity' => [], 'taken' => [], 'online' => []];

        foreach ($groups as $g) {
            $group = ServiceGroup::from($g);
            $sets = $this->duty->dutySets($branch->id, $group, $from, $to);

            foreach ($days as $date => $day) {
                $duty = array_fill(0, $day['cells'], 0);
                foreach ($sets as $set) {
                    foreach ($set->intervals() as $interval) {
                        $first = max(0, intdiv($interval->start - $day['start'] + self::CELL - 1, self::CELL));
                        $last = min($day['cells'], intdiv($interval->end - $day['start'], self::CELL)) - 1; // fully covered only
                        for ($k = $first; $k <= $last; $k++) {
                            $duty[$k]++;
                        }
                    }
                }

                $capacity = [];
                $weekday = $this->clock->weekday($date);
                foreach ($duty as $k => $people) {
                    if (! ($day['open'][$k] ?? false)) {
                        $duty[$k] = $people = 0;
                    }
                    $time = $this->clock->toLocal(CarbonImmutable::createFromTimestampUTC($day['start'] + $k * self::CELL))->format('H:i');
                    $capacity[$k] = OnlineRates::maxOnline($people, $this->rates->ratio($branch->id, $group, $weekday, $time));
                }

                $grid['duty'][$g][$date] = $duty;
                $grid['capacity'][$g][$date] = $capacity;
                $grid['taken'][$g][$date] = array_fill(0, $day['cells'], 0);
                $grid['online'][$g][$date] = array_fill(0, $day['cells'], 0);
            }
        }

        $this->countTaken($branch, $groups, $grid);

        return $grid;
    }

    /**
     * Lines of live appointments (booked / checked in) overlapping the range, in one query.
     *
     * @param  list<string>  $groups
     * @param  array{days: array<string, array{start: int, cells: int, open: array<int, bool>}>, duty: array<string, array<string, array<int, int>>>, capacity: array<string, array<string, array<int, int>>>, taken: array<string, array<string, array<int, int>>>, online: array<string, array<string, array<int, int>>>}  $grid
     */
    private function countTaken(Branch $branch, array $groups, array &$grid): void
    {
        if ($grid['days'] === [] || $groups === []) {
            return;
        }
        $first = reset($grid['days']);
        $last = end($grid['days']);
        $rangeStart = CarbonImmutable::createFromTimestampUTC($first['start']);
        $rangeEnd = CarbonImmutable::createFromTimestampUTC($last['start'] + $last['cells'] * self::CELL);

        $lines = AppointmentItem::query()
            ->join('appointments', 'appointments.id', '=', 'appointment_items.appointment_id')
            ->where('appointment_items.branch_id', $branch->id)
            ->whereIn('appointment_items.service_group', $groups)
            ->whereIn('appointment_items.status', array_map(fn (AppointmentItemStatus $s) => $s->value, AppointmentItemStatus::occupying()))
            ->whereNotIn('appointments.status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->where('appointment_items.starts_at', '<', $rangeEnd)
            ->where('appointment_items.ends_at', '>', $rangeStart)
            ->toBase()
            ->get(['appointment_items.service_group', 'appointment_items.starts_at', 'appointment_items.ends_at', 'appointments.source']);

        foreach ($lines as $line) {
            $g = (string) $line->service_group;
            $start = CarbonImmutable::parse((string) $line->starts_at, 'UTC')->getTimestamp();
            $end = CarbonImmutable::parse((string) $line->ends_at, 'UTC')->getTimestamp();
            $isOnline = $line->source === AppointmentSource::Online->value;

            foreach ($grid['days'] as $date => $day) {
                $dayEnd = $day['start'] + $day['cells'] * self::CELL;
                if ($end <= $day['start'] || $start >= $dayEnd) {
                    continue;
                }
                $firstCell = max(0, intdiv($start - $day['start'], self::CELL));
                $lastCell = min($day['cells'], intdiv($end - $day['start'] + self::CELL - 1, self::CELL)) - 1; // any overlap
                for ($k = $firstCell; $k <= $lastCell; $k++) {
                    $grid['taken'][$g][$date][$k]++;
                    if ($isOnline) {
                        $grid['online'][$g][$date][$k]++;
                    }
                }
            }
        }
    }

    /**
     * @param  array{days: array<string, array{start: int, cells: int, open: array<int, bool>}>, capacity: array<string, array<string, array<int, int>>>, online: array<string, array<string, array<int, int>>>}  $grid
     * @param  array{groups: array<string, array<int, int>>, span: int}  $plan
     */
    private function fitsOnline(array $grid, string $date, int $k, array $plan): bool
    {
        foreach ($plan['groups'] as $g => $demand) {
            foreach ($demand as $j => $need) {
                $cell = $k + $j;
                if (($grid['online'][$g][$date][$cell] ?? 0) + $need > ($grid['capacity'][$g][$date][$cell] ?? 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Start cells whose whole span stays inside opening hours.
     *
     * @param  array{start: int, cells: int, open: array<int, bool>}  $day
     * @return list<int>
     */
    private function starts(array $day, int $span): array
    {
        $starts = [];
        for ($k = 0; $k + $span <= $day['cells']; $k++) {
            $inside = true;
            for ($j = 0; $j < $span; $j++) {
                if (! ($day['open'][$k + $j] ?? false)) {
                    $inside = false;
                    break;
                }
            }
            if ($inside) {
                $starts[] = $k;
            }
        }

        return $starts;
    }

    /**
     * Cells inside the branch's opening hours that day (`opening_hours` keyed by ISO weekday).
     *
     * @return array<int, bool>
     */
    private function openCells(Branch $branch, string $date, int $dayStart, int $cells): array
    {
        $hours = $branch->opening_hours[$this->clock->weekday($date)] ?? []; // JSON keys "1".."7" decode to ints
        $open = new IntervalSet(array_map(
            fn (array $h) => Interval::of($this->clock->toUtc($date, $h[0]), $this->clock->toUtc($date, $h[1])),
            array_values(array_filter($hours, fn (array $h) => $h[0] < $h[1])),
        ));

        $flags = [];
        for ($k = 0; $k < $cells; $k++) {
            $start = $dayStart + $k * self::CELL;
            $flags[$k] = $open->covers(new Interval($start, $start + self::CELL));
        }

        return $flags;
    }

    /**
     * @return array{time: string, starts_at: string}
     */
    private function describe(int $start): array
    {
        $at = CarbonImmutable::createFromTimestampUTC($start);

        return ['time' => $this->clock->toLocal($at)->format('H:i'), 'starts_at' => $at->toIso8601ZuluString()];
    }

    /**
     * @return list<string>
     */
    private function dates(string $from, string $to): array
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        if ($end->lt($start) || $start->diffInDays($end) >= self::MAX_DAYS) {
            throw new InvalidArgumentException('Khoảng ngày không hợp lệ (tối đa '.self::MAX_DAYS.' ngày).');
        }

        $dates = [];
        for ($d = $start; $d->lte($end); $d = $d->addDay()) {
            $dates[] = $d->format('Y-m-d');
        }

        return $dates;
    }
}
