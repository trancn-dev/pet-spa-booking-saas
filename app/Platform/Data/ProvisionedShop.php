<?php

declare(strict_types=1);

namespace App\Platform\Data;

use App\Models\Invitation;
use App\Models\Tenant;

/**
 * Result of ProvisionTenant. The raw token exists only here and in the e-mail (the database keeps
 * its sha256): A4 shows "Sao chép link mời" right after creation, later it needs a new token.
 */
final readonly class ProvisionedShop
{
    public function __construct(
        public Tenant $tenant,
        public Invitation $invitation,
        public string $token,
    ) {}

    public function url(): string
    {
        return route('invitations.show', $this->token);
    }
}
