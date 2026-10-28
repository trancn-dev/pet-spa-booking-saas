<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Appointments\MarkNoShow;
use App\Models\Tenant;
use App\Scheduling\ShopClock;
use Illuminate\Console\Command;

/**
 * End of day, in each shop's own time: lines still booked from earlier days become no_show
 * (db-schema 2.5). Runs hourly so every timezone is covered soon after its midnight; idempotent.
 */
class MarkNoShowCommand extends Command
{
    protected $signature = 'appointments:mark-no-show';

    protected $description = 'Mark booked appointment lines of past shop days as no-show';

    public function handle(MarkNoShow $action): int
    {
        $total = 0;
        Tenant::forEach(function (Tenant $tenant) use ($action, &$total): void {
            if (! $tenant->status->isAccessible()) {
                return;
            }
            [$todayStart] = ShopClock::for($tenant)->localDayRangeUtc(ShopClock::for($tenant)->today()->format('Y-m-d'));
            $total += $action->endOfDay($todayStart);
        });

        $this->info("Marked {$total} line(s) as no-show.");

        return self::SUCCESS;
    }
}
