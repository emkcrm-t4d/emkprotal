<?php

declare(strict_types=1);

namespace App\Domain\Forms\Mail;

use App\Domain\Leads\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your EMK Center Membership Application Received - Ref: ' . $this->lead->uuid,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $applicantName = trim(
            $this->lead->first_name . ' ' .
            ($this->lead->middle_name ? $this->lead->middle_name . ' ' : '') .
            $this->lead->last_name
        );

        return new Content(
            markdown: 'emails.application-confirmation',
            with: [
                'lead' => $this->lead,
                'applicantName' => $applicantName,
                'referenceCode' => $this->lead->uuid,
                'targetSpace' => $this->lead->target_space_code,
                'bkashTxid' => $this->lead->bkash_txid,
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
