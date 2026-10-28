<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Medical (exam, vaccine, lab, imaging) vs grooming (bath, trim). Order matters for lock ordering: medical first.
 */
enum ServiceGroup: string
{
    case Medical = 'medical';
    case Grooming = 'grooming';

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Khám',
            self::Grooming => 'Spa',
        };
    }
}
