<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentKind: string
{
    case Payment = 'payment';
    case Refund = 'refund';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
}
