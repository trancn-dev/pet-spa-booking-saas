<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Rosters\SaveOnlineBookingRate;
use App\Enums\ServiceGroup;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\OnlineBookingRate;
use App\Models\StaffWorkingHour;
use App\Support\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cài đặt › Cơ sở › Tỉ lệ đặt online (task 5.3): per group a default share of on-duty people the
 * Site may book, with overrides by weekday and/or hours. No row for a group = no online booking.
 */
class OnlineBookingRateController extends Controller
{
    public function index(Branch $branch): Response
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $branch]);

        $rates = OnlineBookingRate::query()->where('branch_id', $branch->id)
            ->orderBy('service_group')->orderByRaw('weekday IS NOT NULL')->orderBy('weekday')->orderBy('start_time')->get();

        return Inertia::render('App/Settings/OnlineRates', [
            'branch' => ['id' => $branch->public_id, 'name' => $branch->name],
            'rates' => $rates->map(fn (OnlineBookingRate $r): array => [
                'id' => $r->id,
                'service_group' => $r->service_group->value,
                'ratio' => $r->ratio,
                'weekday' => $r->weekday,
                'start_time' => $r->start_time === null ? null : substr($r->start_time, 0, 5),
                'end_time' => $r->end_time === null ? null : substr($r->end_time, 0, 5),
            ])->values()->all(),
            'groups' => array_map(fn (ServiceGroup $g): array => ['value' => $g->value, 'label' => $g->label()], ServiceGroup::cases()),
            'weekdays' => RosterController::WEEKDAYS,
        ]);
    }

    public function store(Request $request, Branch $branch, SaveOnlineBookingRate $save): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $branch]);

        $save->handle($branch, null, $this->validated($request));

        return back()->with('status', 'Đã lưu tỉ lệ đặt online.');
    }

    public function update(Request $request, Branch $branch, OnlineBookingRate $rate, SaveOnlineBookingRate $save): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $branch]);
        abort_unless($rate->branch_id === $branch->id, 404);

        $save->handle($branch, $rate, $this->validated($request));

        return back()->with('status', 'Đã lưu tỉ lệ đặt online.');
    }

    public function destroy(Branch $branch, OnlineBookingRate $rate): RedirectResponse
    {
        Gate::authorize('manage', [StaffWorkingHour::class, $branch]);
        abort_unless($rate->branch_id === $branch->id, 404);

        $rate->delete();
        ActivityLog::record('branch.online_rate_deleted', $rate, branchId: $branch->id);

        return back()->with('status', 'Đã xóa tỉ lệ.');
    }

    /**
     * @return array{service_group: string, ratio: string, weekday: int|null, start_time: string|null, end_time: string|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'service_group' => ['required', Rule::enum(ServiceGroup::class)],
            'ratio' => ['required', 'decimal:0,3', 'min:0', 'max:1'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ], [
            'ratio.max' => 'Tỉ lệ từ 0 đến 1 (vd 0.5 = một nửa số người trực).',
            'ratio.min' => 'Tỉ lệ từ 0 đến 1 (vd 0.5 = một nửa số người trực).',
        ]);

        return [
            'service_group' => (string) $data['service_group'],
            'ratio' => (string) $data['ratio'],
            'weekday' => isset($data['weekday']) ? (int) $data['weekday'] : null,
            'start_time' => isset($data['start_time']) ? (string) $data['start_time'] : null,
            'end_time' => isset($data['end_time']) ? (string) $data['end_time'] : null,
        ];
    }
}
