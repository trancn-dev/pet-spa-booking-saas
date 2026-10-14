<?php

declare(strict_types=1);

return [
    /*
    | Tenants are resolved from the request host: either an exact match on
    | tenants.domain, or "{slug}.{central_domain}".
    */
    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'localhost'),
];
