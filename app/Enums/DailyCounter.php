<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Names of per-branch daily counters (branch_daily_counters.name).
 */
enum DailyCounter: string
{
    case Queue = 'queue';
    case Invoice = 'invoice';
    case Return = 'return';
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Transfer = 'transfer';
}
