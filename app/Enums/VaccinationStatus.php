<?php

declare(strict_types=1);

namespace App\Enums;

enum VaccinationStatus: string
{
    case Given = 'given';
    case Postponed = 'postponed';
}
