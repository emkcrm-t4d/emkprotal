<?php

declare(strict_types=1);

namespace App\Domain\Leads\Mail;

use App\Domain\Leads\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MissingItemsNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param array<int, string> $missingItems
     */
    public function __construct(
        public Lead $lead,
        public array $missingItems,
        public ?string $customInstructions = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action Required: Outstanding Items for Your EMK Membership Application - Ref: ' . $this->lead->uuid,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $applicantName = $this->lead->full_name;

        return new Content(
            markdown: 'emails.leads.missing-items',
            with: [
                'lead' => $this->lead,
                'applicantName' => $applicantName,
                'referenceCode' => $this->lead->uuid,
                'targetSpace' => $this->lead->target_space_code,
                'missingItems' => $this->missingItems,
                'customInstructions' => $this->customInstructions,
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
