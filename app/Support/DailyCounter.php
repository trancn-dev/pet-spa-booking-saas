<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Per-branch, per-day numbers (queue tickets, invoice codes…) from `branch_daily_counters`
 * (db-schema 2.10). One statement: the upsert locks the row until commit and LAST_INSERT_ID
 * hands back this connection's value, so two check-ins never get the same number.
 * `last_value` is a reserved word in MySQL 8: always backticked.
 */
final class DailyCounter
{
    public static function next(int $tenantId, int $branchId, string $date, string $name): int
    {
        DB::statement(
            'INSERT INTO branch_daily_counters (tenant_id, branch_id, `date`, name, `last_value`) VALUES (?, ?, ?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE `last_value` = LAST_INSERT_ID(`last_value` + 1)',
            [$tenantId, $branchId, $date, $name],
        );

        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;
    }
}
