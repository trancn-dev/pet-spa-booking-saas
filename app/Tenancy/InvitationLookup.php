<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Invitation;
use App\Models\Tenant;

/**
 * `/invite/{token}` is a central route: the shop is only known once the token is found, so this
 * one lookup crosses tenants (by sha256 of the token, a unique column). Everything after it runs
 * inside the invitation's tenant.
 */
class InvitationLookup
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * The invitation and its shop for a raw token (any state), or null. Cancelled shops are gone.
     *
     * @return array{0: Invitation, 1: Tenant}|null
     */
    public function find(string $token): ?array
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        $invitation = $this->context->withoutTenancy(
            fn () => Invitation::query()->where('token_hash', hash('sha256', $token))->first()
        );

        $tenant = $invitation === null ? null : Tenant::query()->find($invitation->tenant_id);

        if ($invitation === null || $tenant === null || ! $tenant->status->isAccessible()) {
            return null;
        }

        return [$invitation, $tenant];
    }

    /**
     * Whether the e-mail has an invitation it can still accept (lets an account without an active
     * shop sign in to accept it).
     */
    public function hasPendingFor(string $email): bool
    {
        return $this->context->withoutTenancy(
            fn () => Invitation::query()
                ->where('email', strtolower($email))
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists()
        );
    }
}
