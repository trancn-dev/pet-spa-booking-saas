<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Rosters\AddTimeOff;
use App\Actions\Rosters\SaveBranchClosure;
use App\Actions\Rosters\SaveWorkingHour;
use App\Authorization\CurrentBranch;
use App\Enums\ServiceGroup;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchClosure;
use App\Models\OnlineBookingRate;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\StaffWorkingHour;
use App\Scheduling\OnlineRates;
use App\Scheduling\Roster;
use App\Scheduling\ShopClock;
use App\Support\ActivityLog;
use App\Tenancy\CurrentStaff;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S8 Lịch trực & ngày nghỉ (task 5.3): a week grid per person for the branch picked in the
 * topbar, time off, closed days, people on duty per group and the online maximum.
 * Everyone sees their own row; `rosters.view` sees everyone, `rosters.manage` edits.
 */
class RosterController extends Controller
{
    public const WEEKDAYS = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

    public function __construct(private readonly CurrentStaff $current) {}

    public function index(Request $request): Response
    {
        $branch = $this->branch();
        $clock = ShopClock::current();
        $monday = $this->monday($request->string('week')->value(), $clock);
        $sunday = $monday->addDays(6);
        [$from, $to] = [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];

        $seeAll = Gate::allows('viewAny', StaffWorkingHour::class);
        $me = $this->current->get();

        $people = Staff::query()->where('status', StaffStatus::Active->value)
            ->where(fn (Builder $q) => $q->where('primary_branch_id', $branch->id)
                ->orWhereHas('branches', fn (Builder $b) => $b->where('branches.id', $branch->id)))
            ->when(! $seeAll, fn (Builder $q) => $q->whereKey($me->id ?? 0))
            ->orderBy('display_name')->get(['id', 'display_name', 'title', 'color']);
        $ids = $people->pluck('id');

        // Every slot of these people in the week, at any branch: overlaps show across branches.
        $slots = StaffWorkingHour::query()->with('branch:id,name')->whereIn('staff_id', $ids)
            ->where(fn (Builder $q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $to))
            ->where(fn (Builder $q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->orderBy('weekday')->orderBy('start_time')->get()->groupBy('staff_id');

        [$rangeStart] = $clock->localDayRangeUtc($from);
        [, $rangeEnd] = $clock->localDayRangeUtc($to);
        $timeOff = StaffTimeOff::query()->whereIn('staff_id', $ids)
            ->where('starts_at', '<', $rangeEnd)->where('ends_at', '>', $rangeStart)->orderBy('starts_at')->get();

        $closures = BranchClosure::query()->where(fn (Builder $q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereBetween('date', [$from, $to])->get()->keyBy(fn (BranchClosure $c) => $c->date->format('Y-m-d'));

        $onDuty = (new Roster($clock))->onDutyPerDay($branch->id, $from, $to);
        $rates = new OnlineRates;

        $days = [];
        for ($d = $monday; $d->lte($sunday); $d = $d->addDay()) {
            $date = $d->format('Y-m-d');
            $weekday = $d->isoWeekday();
            $closure = $closures->get($date);
            $days[] = [
                'date' => $date,
                'weekday' => $weekday,
                'label' => self::WEEKDAYS[$weekday].' '.$d->format('d/m'),
                'closed' => $closure === null ? null : ['id' => $closure->id, 'reason' => $closure->reason, 'whole_shop' => $closure->branch_id === null],
                'on_duty' => $onDuty[$date],
                'max_online' => [
                    'medical' => OnlineRates::maxOnline($onDuty[$date]['medical'], $rates->ratio($branch->id, ServiceGroup::Medical, $weekday)),
                    'grooming' => OnlineRates::maxOnline($onDuty[$date]['grooming'], $rates->ratio($branch->id, ServiceGroup::Grooming, $weekday)),
                ],
            ];
        }

        return Inertia::render('App/Rosters/Index', [
            'branch' => ['id' => $branch->id, 'public_id' => $branch->public_id, 'name' => $branch->name],
            'week' => ['from' => $from, 'to' => $to, 'prev' => $monday->subWeek()->format('Y-m-d'), 'next' => $monday->addWeek()->format('Y-m-d')],
            'days' => $days,
            'people' => $people->map(fn (Staff $s): array => [
                'id' => $s->id,
                'name' => $s->display_name,
                'title' => $s->title,
                'color' => $s->color,
                'slots' => ($slots[$s->id] ?? collect())->map(fn (StaffWorkingHour $h): array => [
                    'id' => $h->id,
                    'weekday' => $h->weekday,
                    'start_time' => substr($h->start_time, 0, 5),
                    'end_time' => substr($h->end_time, 0, 5),
                    'service_group' => $h->service_group?->value,
                    'branch_id' => $h->branch_id,
                    'branch' => $h->branch->name,
                    'effective_from' => $h->effective_from?->format('Y-m-d'),
                    'effective_to' => $h->effective_to?->format('Y-m-d'),
                ])->values()->all(),
                'time_off' => $timeOff->where('staff_id', $s->id)->map(fn (StaffTimeOff $t): array => [
                    'id' => $t->id,
                    'starts_at' => $clock->toLocal($t->starts_at)->format('Y-m-d H:i'),
                    'ends_at' => $clock->toLocal($t->ends_at)->format('Y-m-d H:i'),
                    'reason' => $t->reason,
                    'affected' => AddTimeOff::affected($s->id, $t->starts_at, $t->ends_at)->count(),
                ])->values()->all(),
            ])->values()->all(),
            'rates' => $this->ratesSummary($branch),
            'groups' => array_map(fn (ServiceGroup $g): array => ['value' => $g->value, 'label' => $g->label()], ServiceGroup::cases()),
            'can' => [
                'manage' => Gate::allows('manage', [StaffWorkingHour::class, $branch]),
                'closeShop' => Gate::allows('manage', [StaffWorkingHour::class, $branch]) && $request->user()?->checkPermissionTo('branches.all') === true,
                'seeAll' => $seeAll,
            ],
        ]);
    }

    public function storeSlot(Request $request, SaveWorkingHour $save): RedirectResponse
    {
        $data = $this->validatedSlot($request);
        Gate::authorize('manage', [StaffWorkingHour::class, $data['branch_id']]);

        $save->handle($this->staff((int) $request->integer('staff_id')), null, $data);

        return back()->with('status', 'Đã thêm khung trực.');
    }

    public function updateSlot(Request $request, StaffWorkingHour $slot, SaveWorkingHour $save): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $slot->branch_id]);
        $data = $this->validatedSlot($request);
        Gate::authorize('manage', [StaffWorkingHour::class, $data['branch_id']]);

        $save->handle($this->staff($slot->staff_id), $slot, $data);

        return back()->with('status', 'Đã lưu khung trực.');
    }

    public function destroySlot(StaffWorkingHour $slot): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $slot->branch_id]);

        $slot->delete();
        ActivityLog::record('roster.deleted', $slot, branchId: $slot->branch_id);

        return back()->with('status', 'Đã xóa khung trực.');
    }

    public function storeTimeOff(Request $request, AddTimeOff $add): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $this->branch()]);

        $data = $request->validate([
            'staff_id' => ['required', 'integer'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'starts_time' => ['nullable', 'date_format:H:i'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'ends_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['ends_on.after_or_equal' => 'Ngày kết thúc phải từ ngày bắt đầu trở đi.']);

        [, $affected] = $add->handle($this->staff((int) $data['staff_id']), [
            'starts_on' => (string) $data['starts_on'],
            'starts_time' => isset($data['starts_time']) ? (string) $data['starts_time'] : null,
            'ends_on' => (string) $data['ends_on'],
            'ends_time' => isset($data['ends_time']) ? (string) $data['ends_time'] : null,
            'reason' => isset($data['reason']) ? (string) $data['reason'] : null,
        ], $this->current->get());

        return back()->with('status', $affected->isEmpty()
            ? 'Đã thêm ngày nghỉ.'
            : "Đã thêm ngày nghỉ. {$affected->count()} lịch đã gán người này bị ảnh hưởng: cần đổi người hoặc báo khách.");
    }

    public function destroyTimeOff(StaffTimeOff $timeOff): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $this->branch()]);
        $this->staff($timeOff->staff_id);

        $timeOff->delete();

        return back()->with('status', 'Đã xóa ngày nghỉ.');
    }

    public function storeClosure(Request $request, SaveBranchClosure $save): RedirectResponse
    {
        $branch = $this->branch();
        Gate::authorize('manage', [StaffWorkingHour::class, $branch]);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:255'],
            'whole_shop' => ['boolean'],
        ]);
        $wholeShop = (bool) ($data['whole_shop'] ?? false);
        abort_if($wholeShop && $request->user()?->checkPermissionTo('branches.all') !== true, 403);

        [, $affected] = $save->handle($wholeShop ? null : $branch->id, (string) $data['date'], isset($data['reason']) ? (string) $data['reason'] : null);

        return back()->with('status', $affected === 0
            ? 'Đã đánh dấu ngày nghỉ.'
            : "Đã đánh dấu ngày nghỉ. {$affected} lịch hẹn rơi vào ngày này: cần dời hoặc báo khách.");
    }

    public function destroyClosure(Request $request, BranchClosure $closure): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $closure->branch_id ?? $this->branch()]);
        abort_if($closure->branch_id === null && $request->user()?->checkPermissionTo('branches.all') !== true, 403);

        $closure->delete();

        return back()->with('status', 'Đã bỏ ngày nghỉ.');
    }

    /**
     * @return list<array{group: string, label: string, default: string|null, overrides: int}>
     */
    private function ratesSummary(Branch $branch): array
    {
        $rows = OnlineBookingRate::query()->where('branch_id', $branch->id)->get();

        return array_map(fn (ServiceGroup $g): array => [
            'group' => $g->value,
            'label' => $g->label(),
            'default' => $rows->first(fn ($r) => $r->service_group === $g && $r->weekday === null && $r->start_time === null)?->ratio,
            'overrides' => $rows->filter(fn ($r) => $r->service_group === $g && ($r->weekday !== null || $r->start_time !== null))->count(),
        ], ServiceGroup::cases());
    }

    private function branch(): Branch
    {
        $branch = CurrentBranch::get();
        abort_if($branch === null, 403, 'Chưa chọn cơ sở.');

        return $branch;
    }

    /** A person of this shop working at the current branch (or anywhere with `branches.all`). */
    private function staff(int $id): Staff
    {
        $staff = Staff::query()->findOrFail($id);
        $branch = $this->branch();
        $there = $staff->primary_branch_id === $branch->id || $staff->branches()->where('branches.id', $branch->id)->exists();
        abort_unless($there || CurrentBranch::allows($staff->primary_branch_id), 404);

        return $staff;
    }

    private function monday(string $week, ShopClock $clock): CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) === 1 ? CarbonImmutable::parse($week) : $clock->today();

        return $day->startOfWeek(CarbonImmutable::MONDAY);
    }

    /**
     * @return array{branch_id: int, weekday: int, start_time: string, end_time: string, service_group: string|null, effective_from: string|null, effective_to: string|null}
     */
    private function validatedSlot(Request $request): array
    {
        $data = $request->validate([
            'staff_id' => [$request->isMethod('post') ? 'required' : 'nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'service_group' => ['nullable', Rule::enum(ServiceGroup::class)],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d'],
        ], ['start_time.date_format' => 'Giờ dạng 08:30.', 'end_time.date_format' => 'Giờ dạng 17:30.']);

        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : $this->branch()->id;
        abort_unless(Branch::query()->whereKey($branchId)->exists(), 404);

        return [
            'branch_id' => $branchId,
            'weekday' => (int) $data['weekday'],
            'start_time' => (string) $data['start_time'],
            'end_time' => (string) $data['end_time'],
            'service_group' => isset($data['service_group']) ? (string) $data['service_group'] : null,
            'effective_from' => isset($data['effective_from']) ? (string) $data['effective_from'] : null,
            'effective_to' => isset($data['effective_to']) ? (string) $data['effective_to'] : null,
        ];
    }
}
