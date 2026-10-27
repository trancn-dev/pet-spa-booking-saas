<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentItemStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Statuses that hold a capacity slot.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Booked, self::CheckedIn];
    }
}
