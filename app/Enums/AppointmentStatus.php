<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentStatus: string
{
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Đã xác nhận',
            self::CheckedIn => 'Đã đến',
            self::Cancelled => 'Đã hủy',
            self::NoShow => 'Không đến',
        };
    }
}
