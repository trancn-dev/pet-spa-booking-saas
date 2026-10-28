<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Actions\Invitations\IssueInvitation;
use App\Models\Invitation;
use App\Models\Platform\PlatformAdmin;
use App\Models\Tenant;
use App\Platform\Audit;
use App\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

/**
 * A4 "Gửi lại lời mời" / "Sao chép link mời": a new token for the owner's invitation (the old
 * link stops working), mailed or only returned for copying. Refused once the owner has joined.
 */
class ResendOwnerInvitation
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly IssueInvitation $invitations,
        private readonly ProvisionTenant $provision,
    ) {}

    /**
     * @return string the new invitation URL
     */
    public function handle(PlatformAdmin $by, Tenant $tenant, bool $mail = true): string
    {
        [$invitation, $token] = $this->context->runAs($tenant, function (): array {
            $invitation = Invitation::query()->whereJsonContains('role_names', 'owner')->latest('id')->lockForUpdate()->first();

            if ($invitation === null || $invitation->accepted_at !== null) {
                throw ValidationException::withMessages(['invitation' => 'Chủ shop đã nhận lời mời.']);
            }

            $invitation->forceFill(['revoked_at' => null]);

            return [$invitation, $this->invitations->rotate($invitation)];
        });

        $url = route('invitations.show', $token);

        if ($mail) {
            $this->provision->mail($tenant, $invitation, $url, $by->name);
        }

        Audit::record($by, $mail ? 'tenant.invitation_resent' : 'tenant.invitation_link_copied', $invitation, ['email' => $invitation->email], $tenant->getKey());

        return $url;
    }
}
