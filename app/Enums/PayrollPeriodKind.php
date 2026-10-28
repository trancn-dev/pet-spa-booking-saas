<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * GĐ3.
 */
enum PayrollPeriodKind: string
{
    case Week = 'week';
    case Month = 'month';
}
