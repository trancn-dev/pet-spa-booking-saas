<?php

declare(strict_types=1);

namespace App\Enums;

enum PlatformAdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Support = 'support';
}
