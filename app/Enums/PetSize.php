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

    public function label(): string
    {
        return match ($this) {
            self::Xs => 'XS (rất nhỏ)',
            self::S => 'S (nhỏ)',
            self::M => 'M (vừa)',
            self::L => 'L (lớn)',
            self::Xl => 'XL (rất lớn)',
        };
    }
}
