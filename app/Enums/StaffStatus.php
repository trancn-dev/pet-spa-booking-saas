<?php

declare(strict_types=1);

namespace App\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
