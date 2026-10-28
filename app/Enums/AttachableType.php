<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachableType: string
{
    case MedicalEntry = 'medical_entry';
    case Visit = 'visit';
}
