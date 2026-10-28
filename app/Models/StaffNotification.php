<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Laravel database notification, tenant-stamped. Notifiable is always `staff` (never `users`),
 * so someone working at two shops never sees the other shop's notifications. Wired up in task 6.1.
 */
class StaffNotification extends DatabaseNotification
{
    use BelongsToTenant;
}
