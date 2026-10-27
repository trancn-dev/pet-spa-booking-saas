<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Derived from accepted_at / revoked_at / expires_at (not stored).
 */
enum InvitationState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chưa nhận lời mời',
            self::Accepted => 'Đã nhận',
            self::Revoked => 'Đã thu hồi',
            self::Expired => 'Đã hết hạn',
        };
    }
}
