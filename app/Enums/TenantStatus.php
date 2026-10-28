<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Shop lifecycle (db-schema 2.1): trial / active → past due (7 days' grace, fully usable, red
 * banner) → suspended (viewable, no writes, Site stops taking bookings, no customer reminders)
 * → active again right after an extension. Cancelled = the shop is gone (404).
 */
enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /** Can be opened at /app/{slug}. */
    public function isAccessible(): bool
    {
        return $this !== self::Cancelled;
    }

    /** Viewable only: writes get 423 (EnsureTenantActive), the Site takes no bookings. */
    public function isReadOnly(): bool
    {
        return $this === self::Suspended;
    }

    /** Customer reminders / booking messages go out only for these. */
    public function sendsReminders(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Dùng thử',
            self::Active => 'Hoạt động',
            self::PastDue => 'Quá hạn',
            self::Suspended => 'Tạm khóa',
            self::Cancelled => 'Đã hủy',
        };
    }
}
