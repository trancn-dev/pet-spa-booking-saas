<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentChangeAction: string
{
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Restored = 'restored';
    case CheckedIn = 'checked_in';
    case NoShow = 'no_show';
    case Reassigned = 'reassigned';
    case ItemAdded = 'item_added';
    case ItemRemoved = 'item_removed';
}
