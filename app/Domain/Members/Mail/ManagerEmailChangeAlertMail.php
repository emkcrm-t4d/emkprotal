<?php

declare(strict_types=1);

namespace App\Domain\Members\Mail;

use App\Domain\Members\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerEmailChangeAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Member $member,
        public string $newEmail,
        public string $justification
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[EMK Member Portal] Security Alert: Email Change Requested for {$this->member->member_id}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.members.manager-email-change-alert',
            with: [
                'member' => $this->member,
                'memberName' => $this->member->full_name,
                'memberId' => $this->member->member_id,
                'currentEmail' => $this->member->email,
                'newEmail' => $this->newEmail,
                'justification' => $this->justification,
                'timestamp' => now()->format('F d, Y H:i:s T'),
                'actionUrl' => route('crm.members.change_requests', ['workspace' => $this->member->workspace_id]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
