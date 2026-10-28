<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Zalo ZNS first, SMS as fallback; never both.
 */
enum MessageChannel: string
{
    case Zalo = 'zalo';
    case Sms = 'sms';
}
