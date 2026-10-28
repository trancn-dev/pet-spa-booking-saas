<?php

declare(strict_types=1);

return [
    /*
    | Booking rules that are not per-shop settings yet (task 5.4).
    | online_lead_minutes: the Site offers starts from now + this many minutes.
    */
    'online_lead_minutes' => (int) env('BOOKING_ONLINE_LEAD_MINUTES', 60),
];
