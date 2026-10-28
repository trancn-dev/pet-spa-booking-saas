<?php

declare(strict_types=1);

namespace App\Platform\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * F20: "còn 7 ngày" reminder to the shop owners. Plain values only (queued).
 */
class PlanExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $shopName,
        public readonly string $expiresOn,
        public readonly int $daysLeft,
        public readonly bool $trial,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->trial ? 'Bản dùng thử' : 'Gói').' PawBook của '.$this->shopName.' sắp hết hạn');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.plan-expiring');
    }
}
