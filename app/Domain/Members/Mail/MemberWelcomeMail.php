<?php

declare(strict_types=1);

namespace App\Domain\Members\Mail;

use App\Domain\Members\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to EMK Center - Your Membership Credentials and Activation',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.members.welcome',
            with: [
                'member' => $this->member,
                'memberName' => $this->member->full_name,
                'memberId' => $this->member->member_id,
                'startDate' => $this->member->start_date?->format('F d, Y'),
                'expiryDate' => $this->member->expiry_date?->format('F d, Y'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
