<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shop lifecycle (db-schema 2.1).
 */
enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Shops in these states can be opened at /app/{slug}. Suspended shops become read-only in task 4.5;
     * until then they stay closed.
     */
    public function isAccessible(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
