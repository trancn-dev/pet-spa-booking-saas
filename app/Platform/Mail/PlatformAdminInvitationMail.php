<?php

declare(strict_types=1);

namespace App\Platform\Mail;

use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\PlatformAdminInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PlatformAdminInvitationMail extends Mailable
{
    public function __construct(
        public readonly PlatformAdminInvitation $invitation,
        public readonly string $url,
        public readonly PlatformAdmin $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Lời mời quản trị PawBook');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.platform-admin-invitation');
    }
}
