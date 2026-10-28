<?php

declare(strict_types=1);

namespace App\Enums;

enum VisitStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Đang chờ',
            self::InProgress => 'Đang làm',
            self::Done => 'Xong',
            self::Cancelled => 'Đã hủy',
        };
    }
}
