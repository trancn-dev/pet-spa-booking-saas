<?php

declare(strict_types=1);

use App\Scheduling\ShopClock;
use Carbon\CarbonImmutable;

/*
| Task 5.3: shop time ↔ UTC per date (db-schema 5.4), never a fixed offset.
| America/New_York: DST starts 2026-03-08 (02:00 → 03:00), ends 2026-11-01 (02:00 → 01:00).
*/

it('converts a 09:00 shift to the right UTC hour on each side of the DST switch', function () {
    $ny = new ShopClock('America/New_York');

    expect($ny->toUtc('2026-03-07', '09:00')->toIso8601String())->toBe('2026-03-07T14:00:00+00:00')
        ->and($ny->toUtc('2026-03-08', '09:00')->toIso8601String())->toBe('2026-03-08T13:00:00+00:00')
        ->and($ny->toUtc('2026-03-08', '17:00')->toIso8601String())->toBe('2026-03-08T21:00:00+00:00')
        ->and($ny->toUtc('2026-10-31', '09:00')->toIso8601String())->toBe('2026-10-31T13:00:00+00:00')
        ->and($ny->toUtc('2026-11-01', '09:00')->toIso8601String())->toBe('2026-11-01T14:00:00+00:00');
});

it('gives DST days their real length', function () {
    $ny = new ShopClock('America/New_York');

    [$springStart, $springEnd] = $ny->localDayRangeUtc('2026-03-08');
    [$fallStart, $fallEnd] = $ny->localDayRangeUtc('2026-11-01');
    [$plainStart, $plainEnd] = $ny->localDayRangeUtc('2026-06-15');

    expect($springStart->toIso8601String())->toBe('2026-03-08T05:00:00+00:00')
        ->and($springEnd->toIso8601String())->toBe('2026-03-09T04:00:00+00:00')
        ->and($springStart->diffInHours($springEnd))->toBe(23.0)
        ->and($fallStart->diffInHours($fallEnd))->toBe(25.0)
        ->and($plainStart->diffInHours($plainEnd))->toBe(24.0);
});

it('works in Vietnam time and answers today and weekdays locally', function () {
    $hcm = new ShopClock('Asia/Ho_Chi_Minh');

    expect($hcm->toUtc('2026-10-28', '09:00')->toIso8601String())->toBe('2026-10-28T02:00:00+00:00')
        // 18:00 UTC is already the next morning in Vietnam
        ->and($hcm->today(CarbonImmutable::parse('2026-10-28T18:00:00Z'))->format('Y-m-d'))->toBe('2026-10-29')
        ->and($hcm->toLocal('2026-10-28 18:30:00')->format('Y-m-d H:i'))->toBe('2026-10-29 01:30')
        ->and($hcm->weekday('2026-10-26'))->toBe(1)
        ->and($hcm->weekday('2026-11-01'))->toBe(7);
});
