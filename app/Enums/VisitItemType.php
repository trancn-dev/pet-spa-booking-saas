<?php

declare(strict_types=1);

namespace App\Enums;

enum VisitItemType: string
{
    case Service = 'service';
    case Medication = 'medication';
    case Consumable = 'consumable';
}
