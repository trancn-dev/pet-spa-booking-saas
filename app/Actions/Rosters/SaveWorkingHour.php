<?php

declare(strict_types=1);

namespace App\Actions\Rosters;

use App\Enums\ServiceGroup;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One weekly slot of a person's roster (S8, db-schema 2.2): a branch, a weekday, local start/end,
 * the group it counts for (medical / grooming / none) and an optional effective range.
 *
 * A person never has two overlapping slots, even at different branches: checked here with the
 * staff row locked, so two saves for the same person run one after the other.
 */
class SaveWorkingHour
{
    public const OVERLAP = 'Trùng giờ với khung :slot của người này.';

    /**
     * @param  array{branch_id: int, weekday: int, start_time: string, end_time: string, service_group: string|null, effective_from: string|null, effective_to: string|null}  $data
     */
    public function handle(Staff $staff, ?StaffWorkingHour $slot, array $data): StaffWorkingHour
    {
        $start = self::time($data['start_time']);
        $end = self::time($data['end_time']);
        if ($start >= $end) {
            throw ValidationException::withMessages(['end_time' => 'Giờ kết thúc phải sau giờ bắt đầu (ca qua nửa đêm: tách thành 2 khung).']);
        }
        if ($data['effective_from'] !== null && $data['effective_to'] !== null && $data['effective_to'] < $data['effective_from']) {
            throw ValidationException::withMessages(['effective_to' => 'Ngày kết thúc phải sau ngày áp dụng.']);
        }

        return DB::transaction(function () use ($staff, $slot, $data, $start, $end): StaffWorkingHour {
            $staff = Staff::query()->whereKey($staff->getKey())->lockForUpdate()->firstOrFail();

            $worksThere = $staff->primary_branch_id === $data['branch_id']
                || $staff->branches()->where('branches.id', $data['branch_id'])->exists();
            if (! $worksThere) {
                throw ValidationException::withMessages(['branch_id' => 'Người này không làm ở cơ sở đó (sửa cơ sở ở hồ sơ nhân viên).']);
            }

            $clash = StaffWorkingHour::query()->with('branch:id,name')
                ->where('staff_id', $staff->id)
                ->when($slot !== null, fn ($q) => $q->whereKeyNot($slot?->id))
                ->where('weekday', $data['weekday'])
                ->where('start_time', '<', $end)->where('end_time', '>', $start)
                // Effective ranges overlap (NULL = open-ended).
                ->when($data['effective_to'] !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $data['effective_to'])))
                ->when($data['effective_from'] !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $data['effective_from'])))
                ->first();
            if ($clash !== null) {
                $label = substr($clash->start_time, 0, 5).'–'.substr($clash->end_time, 0, 5).' ở '.$clash->branch->name;
                throw ValidationException::withMessages(['start_time' => str_replace(':slot', $label, self::OVERLAP)]);
            }

            $attributes = [
                'branch_id' => $data['branch_id'],
                'weekday' => $data['weekday'],
                'start_time' => $start,
                'end_time' => $end,
                'service_group' => $data['service_group'] === null ? null : ServiceGroup::from($data['service_group']),
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'],
            ];

            if ($slot === null) {
                $slot = StaffWorkingHour::query()->create(['staff_id' => $staff->id, ...$attributes]);
            } else {
                $slot->update($attributes);
            }
            ActivityLog::record('roster.saved', $slot, ['staff' => $staff->display_name, 'weekday' => $data['weekday'], 'time' => "{$start}-{$end}"], branchId: $data['branch_id']);

            return $slot;
        });
    }

    /** "9:00" / "09:00" / "09:00:00" → "09:00:00". */
    public static function time(string $value): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m) !== 1 || (int) $m[1] > 23 || (int) $m[2] > 59) {
            throw ValidationException::withMessages(['start_time' => 'Giờ không hợp lệ.']);
        }

        return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
    }
}
