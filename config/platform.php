<?php

declare(strict_types=1);

return [
    // Session cookie of the /admin area, distinct from the CMS cookie: signing out of one
    // never signs out of the other.
    'session_cookie' => 'pawbook_admin_session',

    // Skip the TOTP step for admins without 2FA. Only honoured when APP_ENV is local or demo
    // (App\Platform\TwoFactor::canSkip); ignored everywhere else, production included.
    'skip_two_factor' => (bool) env('PLATFORM_SKIP_2FA', false),

    'invitation_ttl_hours' => 72,

    // Shown on the admin sidebar badge.
    'environment_label' => env('PLATFORM_ENV_LABEL'),
];
