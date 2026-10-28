<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceLineType: string
{
    case VisitItem = 'visit_item';
    case Product = 'product';
    case Custom = 'custom';
}
