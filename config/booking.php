<?php

declare(strict_types=1);

return [
    /*
    | Booking rules that are not per-shop settings yet (task 5.4).
    | online_lead_minutes: the Site offers starts from now + this many minutes.
    */
    'online_lead_minutes' => (int) env('BOOKING_ONLINE_LEAD_MINUTES', 60),

    // "Hoàn tác" after a cancellation (S3 toast), task 5.6.
    'undo_seconds' => (int) env('BOOKING_UNDO_SECONDS', 5),
];
