<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\ServiceGroup;
use App\Models\Branch;
use App\Models\OnlineBookingRate;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Online booking share for a branch × group (db-schema 2.5): one default row (no weekday, no hours)
 * plus overrides by weekday and/or hours. Same scope twice is refused, checked under the branch
 * row lock (the nullable columns make a UNIQUE index useless here).
 */
class SaveOnlineBookingRate
{
    public const DUPLICATE = 'Đã có tỉ lệ cho đúng nhóm, thứ, khung giờ này.';

    /**
     * @param  array{service_group: string, ratio: string, weekday: int|null, start_time: string|null, end_time: string|null}  $data
     */
    public function handle(Branch $branch, ?OnlineBookingRate $rate, array $data): OnlineBookingRate
    {
        $group = ServiceGroup::from($data['service_group']);
        $start = $data['start_time'] === null ? null : SaveWorkingHour::time($data['start_time']);
        $end = $data['end_time'] === null ? null : SaveWorkingHour::time($data['end_time']);

        if (($start === null) !== ($end === null)) {
            throw ValidationException::withMessages(['start_time' => 'Nhập đủ giờ bắt đầu và kết thúc, hoặc để trống cả hai.']);
        }
        if ($start !== null && $start >= $end) {
            throw ValidationException::withMessages(['end_time' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
        }

        return DB::transaction(function () use ($branch, $rate, $data, $group, $start, $end): OnlineBookingRate {
            Branch::query()->whereKey($branch->getKey())->lockForUpdate()->first();

            $duplicate = OnlineBookingRate::query()->where('branch_id', $branch->id)->where('service_group', $group->value)
                ->when($rate !== null, fn ($q) => $q->whereKeyNot($rate?->id))
                ->when($data['weekday'] === null, fn ($q) => $q->whereNull('weekday'), fn ($q) => $q->where('weekday', $data['weekday']))
                ->when($start === null, fn ($q) => $q->whereNull('start_time'), fn ($q) => $q->where('start_time', $start)->where('end_time', $end))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['ratio' => self::DUPLICATE]);
            }

            $attributes = [
                'service_group' => $group,
                'ratio' => $data['ratio'],
                'weekday' => $data['weekday'],
                'start_time' => $start,
                'end_time' => $end,
            ];

            if ($rate === null) {
                $rate = OnlineBookingRate::query()->create(['branch_id' => $branch->id, ...$attributes]);
            } else {
                $rate->update($attributes);
            }
            ActivityLog::record('branch.online_rate_saved', $rate, ['group' => $group->value, 'ratio' => $data['ratio'], 'weekday' => $data['weekday']], branchId: $branch->id);

            return $rate;
        });
    }
}
