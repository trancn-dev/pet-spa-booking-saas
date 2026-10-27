<?php

declare(strict_types=1);

namespace App\Enums;

enum PetSize: string
{
    case Xs = 'xs';
    case S = 's';
    case M = 'm';
    case L = 'l';
    case Xl = 'xl';
}
