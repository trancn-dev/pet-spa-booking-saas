<?php

declare(strict_types=1);

namespace App\Enums;

enum AttachmentKind: string
{
    case LabResult = 'lab_result';
    case Imaging = 'imaging';
    case IntakePhoto = 'intake_photo';
    case ReturnPhoto = 'return_photo';
    case Other = 'other';
}
