<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Invitation to join a shop (owner from /admin, staff from the CMS). Carries plain values only:
 * a queued mail must not re-load tenant-owned models outside a tenant context.
 */
class ShopInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $shopName,
        public readonly string $roleLabel,
        public readonly string $url,
        public readonly string $expiresAt,
        public readonly string $invitedBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Lời mời tham gia {$this->shopName} trên PawBook");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.shop-invitation');
    }
}
