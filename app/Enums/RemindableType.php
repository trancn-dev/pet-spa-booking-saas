<?php

declare(strict_types=1);

namespace App\Enums;

enum RemindableType: string
{
    case Appointment = 'appointment';
    case PetVaccination = 'pet_vaccination';
    case MedicalEntry = 'medical_entry';
    case Visit = 'visit';
}
