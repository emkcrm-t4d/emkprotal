<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\IdCards\Models\IdCard;
use App\Domain\Members\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CardReadyNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public IdCard $card
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your EMK Center Membership Card is Ready for Pickup! - {$this->member->member_id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.card-ready-notification',
            with: [
                'member' => $this->member,
                'card' => $this->card,
                'memberName' => $this->member->full_name,
                'memberId' => $this->member->member_id,
                'cardNumber' => $this->card->card_number,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
