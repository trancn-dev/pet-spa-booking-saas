<?php

declare(strict_types=1);

namespace App\Scheduling;

use App\Enums\ServiceGroup;
use App\Models\OnlineBookingRate;
use Illuminate\Support\Collection;

/**
 * Share of on-duty people the Site may book, per branch × group (db-schema 2.5). Most specific
 * row wins: weekday + hours → weekday → hours → the default row. No row = no online booking.
 */
final class OnlineRates
{
    /** @var array<string, Collection<int, OnlineBookingRate>> */
    private array $rows = [];

    public function ratio(int $branchId, ServiceGroup $group, int $weekday, ?string $time = null): ?string
    {
        return $this->match($branchId, $group, $weekday, $time)?->ratio;
    }

    public function match(int $branchId, ServiceGroup $group, int $weekday, ?string $time = null): ?OnlineBookingRate
    {
        $time = $time === null ? null : substr($time, 0, 5);

        return $this->rows($branchId, $group)
            ->filter(fn (OnlineBookingRate $r) => ($r->weekday === null || $r->weekday === $weekday)
                && ($r->start_time === null || ($time !== null && substr($r->start_time, 0, 5) <= $time && $time < substr((string) $r->end_time, 0, 5))))
            ->sortByDesc(fn (OnlineBookingRate $r) => ($r->weekday !== null ? 2 : 0) + ($r->start_time !== null ? 1 : 0))
            ->first();
    }

    /**
     * "Đặt online tối đa" = floor(on duty × ratio) (db-schema 5.1), in whole bookings.
     *
     * The ratio is DECIMAL(4,3), so a fraction like 1/3 is stored as 0.333 (within ±0.0005 of what
     * the owner meant). Flooring 3 × 0.333 = 0.999 would give 0, while design S12 promises
     * "3 bác sĩ trực × 1/3 = 1 lịch online cùng lúc": the floor allows for that storage error.
     */
    public static function maxOnline(int $onDuty, ?string $ratio): int
    {
        if ($ratio === null || $onDuty <= 0) {
            return 0;
        }

        // Integer maths on thousandths, no float rounding.
        [$whole, $fraction] = array_pad(explode('.', $ratio, 2), 2, '');
        $thousandths = (int) $whole * 1000 + (int) str_pad(substr($fraction, 0, 3), 3, '0');

        // floor(n × (ratio + 0.0005)) = floor((2 n × thousandths + n) / 2000)
        return intdiv(2 * $onDuty * $thousandths + $onDuty, 2000);
    }

    /**
     * @return Collection<int, OnlineBookingRate>
     */
    private function rows(int $branchId, ServiceGroup $group): Collection
    {
        return $this->rows[$branchId.':'.$group->value] ??= OnlineBookingRate::query()
            ->where('branch_id', $branchId)->where('service_group', $group->value)->get();
    }
}
