<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderPurpose: string
{
    case AppointmentConfirm = 'appointment_confirm';
    case Appointment24h = 'appointment_24h';
    case Appointment2h = 'appointment_2h';
    case VaccineDue = 'vaccine_due';
    case FollowUp = 'follow_up';
    case PickupReady = 'pickup_ready';
}
