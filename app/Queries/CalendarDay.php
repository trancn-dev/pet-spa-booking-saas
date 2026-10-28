<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\AppointmentItemStatus;
use App\Enums\ServiceGroup;
use App\Enums\StaffStatus;
use App\Http\Resources\PetSummary;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Scheduling\AvailabilityService;
use App\Scheduling\Roster;
use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Data of S1 (task 5.7) for one branch and a range of shop days, in a fixed number of queries:
 * appointment lines (with appointment, customer, pet, service, people), rosters, time off,
 * closures, and the capacity of every 15-minute cell. Times are shop-local `HH:MM`.
 *
 * A block = the lines of one appointment, one group, one person: "14:00 · Mochi, Bơ". An
 * appointment with an exam and a spa shows two linked blocks.
 */
final class CalendarDay
{
    public const FROM = '07:00';

    public const TO = '21:00';

    public function __construct(
        private readonly ShopClock $clock,
        private readonly AvailabilityService $availability,
        private readonly Roster $roster,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function day(Branch $branch, string $date): array
    {
        [$from, $to] = $this->clock->localDayRangeUtc($date);
        $items = $this->items($branch, $from, $to);
        $closure = BranchClosure::query()->where('date', $date)
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))->first();

        // Columns: people on duty here today (any group) and people with lines today.
        $occurrences = collect($this->roster->occurrences($branch->id, $date, $date));
        $staffIds = $occurrences->pluck('slot.staff_id')->merge($items->pluck('assigned_staff_id'))->filter()->unique()->values();
        $staff = Staff::query()->whereKey($staffIds)->where('status', StaffStatus::Active->value)->orderBy('display_name')->get(['id', 'display_name', 'color', 'title']);
        $off = StaffTimeOff::query()->whereIn('staff_id', $staffIds)->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get();

        $capacity = $this->availability->slotsForStaff($branch, [], $date, $date)[$date] ?? [];
        $hours = $branch->opening_hours[$this->clock->weekday($date)] ?? [];

        return [
            'date' => $date,
            'weekday' => $this->clock->weekday($date),
            'closed' => $closure === null ? null : ['reason' => $closure->reason, 'whole_shop' => $closure->branch_id === null],
            'open' => array_map(fn (array $h) => ['start' => $h[0], 'end' => $h[1]], $hours),
            'staff' => $staff->map(function (Staff $s) use ($occurrences, $off, $items): array {
                $slots = $occurrences->filter(fn (array $o) => $o['slot']->staff_id === $s->id);

                return [
                    'id' => $s->id,
                    'name' => $s->display_name,
                    'title' => $s->title,
                    'color' => $s->color,
                    'groups' => $slots->map(fn (array $o) => $o['slot']->service_group?->value)->filter()->unique()->values()->all(),
                    'slots' => $slots->map(fn (array $o) => [
                        'start' => $this->local($o['starts_at']), 'end' => $this->local($o['ends_at']), 'group' => $o['slot']->service_group?->value,
                    ])->values()->all(),
                    'off' => $off->where('staff_id', $s->id)->map(fn (StaffTimeOff $t) => [
                        'start' => $this->local($t->starts_at), 'end' => $this->local($t->ends_at), 'reason' => $t->reason,
                    ])->values()->all(),
                    'count' => $items->where('assigned_staff_id', $s->id)->where('status', '!=', AppointmentItemStatus::Cancelled)->pluck('appointment_id')->unique()->count(),
                ];
            })->values()->all(),
            'blocks' => $this->blocks($items),
            'capacity' => array_map(fn (array $c) => [
                'time' => $c['time'],
                'medical' => ['taken' => $c['groups']['medical']['taken'], 'on_duty' => $c['groups']['medical']['on_duty']],
                'grooming' => ['taken' => $c['groups']['grooming']['taken'], 'on_duty' => $c['groups']['grooming']['on_duty']],
            ], $capacity),
        ];
    }

    /**
     * Seven days from `$monday`, blocks only (the week view).
     *
     * @return list<array{date: string, blocks: list<array<string, mixed>>}>
     */
    public function week(Branch $branch, string $monday): array
    {
        $start = CarbonImmutable::parse($monday);
        [$from] = $this->clock->localDayRangeUtc($monday);
        [, $to] = $this->clock->localDayRangeUtc($start->addDays(6)->format('Y-m-d'));
        $items = $this->items($branch, $from, $to)->groupBy(fn (AppointmentItem $i) => $this->clock->toLocal($i->starts_at)->format('Y-m-d'));

        $days = [];
        for ($d = 0; $d < 7; $d++) {
            $date = $start->addDays($d)->format('Y-m-d');
            $days[] = ['date' => $date, 'blocks' => $this->blocks($items->get($date, collect()))];
        }

        return $days;
    }

    /**
     * @return Collection<int, AppointmentItem>
     */
    private function items(Branch $branch, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AppointmentItem::query()
            ->with([
                'appointment:id,public_id,customer_id,source,status,preferred_staff_id,send_reminders,starts_at',
                // Deleted profiles still show on past and booked blocks.
                'appointment.customer' => fn ($q) => $q->withTrashed()->select(['id', 'name', 'phone']),
                'appointment.preferredStaff:id,display_name',
                'pet' => fn ($q) => $q->withTrashed()->select(['id', 'public_id', 'name', 'allergies', 'chronic_conditions', 'behavior_notes']),
                'service' => fn ($q) => $q->withTrashed()->select(['id', 'name']),
            ])
            ->where('branch_id', $branch->id)
            ->where('starts_at', '>=', $from)->where('starts_at', '<', $to)
            ->orderBy('starts_at')->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, AppointmentItem>  $items
     * @return list<array<string, mixed>>
     */
    private function blocks(Collection $items): array
    {
        $groupsOf = $items->groupBy('appointment_id')->map(fn (Collection $lines) => $lines->pluck('service_group')->map(fn (ServiceGroup $g) => $g->value)->unique()->count());

        return $items->groupBy(fn (AppointmentItem $i) => $i->appointment_id.'|'.$i->service_group->value.'|'.($i->assigned_staff_id ?? 0))
            ->map(function (Collection $lines) use ($groupsOf): array {
                /** @var AppointmentItem $first */
                $first = $lines->first();
                $appointment = $first->appointment;
                $live = $lines->reject(fn (AppointmentItem $l) => in_array($l->status, [AppointmentItemStatus::Cancelled, AppointmentItemStatus::NoShow], true));
                $shown = $live->isEmpty() ? $lines : $live;

                return [
                    'key' => $first->appointment_id.'-'.$first->service_group->value.'-'.($first->assigned_staff_id ?? 0),
                    'appointment' => $appointment->public_id,
                    'group' => $first->service_group->value,
                    'staff_id' => $first->assigned_staff_id,
                    'start' => $this->local($first->starts_at),
                    'end' => $this->local($shown->max('ends_at')),
                    'service_end' => $this->local($first->starts_at->copy()->addMinutes((int) $shown->max('duration_min'))),
                    'status' => $appointment->status->value,
                    'line_status' => $live->isEmpty() ? $first->status->value : ($live->contains(fn ($l) => $l->status === AppointmentItemStatus::CheckedIn) ? 'checked_in' : 'booked'),
                    'source' => $appointment->source->value,
                    'customer' => $appointment->customer->name,
                    'preferred' => $appointment->preferredStaff?->display_name,
                    'no_reminders' => ! $appointment->send_reminders,
                    'linked' => ($groupsOf[$first->appointment_id] ?? 1) > 1,
                    'pets' => $lines->map(fn (AppointmentItem $l) => [
                        'name' => $l->pet->name, 'service' => $l->service->name, 'status' => $l->status->value, 'alerts' => PetSummary::alerts($l->pet),
                    ])->values()->all(),
                ];
            })->values()->all();
    }

    private function local(DateTimeInterface $instant): string
    {
        return $this->clock->toLocal(CarbonImmutable::instance($instant))->format('H:i');
    }
}
