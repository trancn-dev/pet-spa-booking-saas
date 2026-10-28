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

    // Shop invitations (owner from /admin, staff from the CMS): one use, 7 days.
    'shop_invitation_ttl_days' => 7,

    // A3: default trial length and the Site address shown as a preview of the slug.
    'trial_days' => 14,
    'site_host' => env('PLATFORM_SITE_HOST', 'pawbook.vn'),

    // Plan lifecycle (db-schema 2.1, task 4.5): warn N days before expiry; past due for N days
    // (grace, fully usable) before the shop is suspended.
    'expiry_warning_days' => 7,
    'grace_days' => 7,

    // Shown on the admin sidebar badge.
    'environment_label' => env('PLATFORM_ENV_LABEL'),
];
