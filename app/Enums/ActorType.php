<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who triggered a change.
 */
enum ActorType: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case System = 'system';
}
