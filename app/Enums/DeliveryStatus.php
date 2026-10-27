<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
