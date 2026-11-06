<?php

declare(strict_types=1);

namespace App\Domain\Mail\Mailables;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TemplatedLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $htmlContent,
        public string $textContent = '',
        public string $messageIdHeader = '',
        public ?string $inReplyToHeader = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent !== '' ? $this->htmlContent : '<p>' . e($this->textContent !== '' ? $this->textContent : $this->subjectLine) . '</p>',
        );
    }

    /**
     * Get the message headers.
     */
    public function headers(): Headers
    {
        $textHeaders = [];

        if ($this->messageIdHeader !== '') {
            $textHeaders['X-CRM-Message-ID'] = $this->messageIdHeader;
        }

        if ($this->inReplyToHeader !== null && $this->inReplyToHeader !== '') {
            $textHeaders['In-Reply-To'] = $this->inReplyToHeader;
        }

        return new Headers(
            messageId: $this->messageIdHeader !== '' ? trim($this->messageIdHeader, '<>') : null,
            references: $this->inReplyToHeader !== null && $this->inReplyToHeader !== ''
                ? [trim($this->inReplyToHeader, '<>')]
                : [],
            text: $textHeaders,
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
