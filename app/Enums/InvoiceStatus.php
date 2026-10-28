<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Completed => 'Hoàn thành',
            self::Void => 'Đã hủy',
        };
    }
}
