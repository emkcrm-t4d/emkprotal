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
        $rawHtml = $this->htmlContent !== ''
            ? $this->htmlContent
            : '<p>' . e($this->textContent !== '' ? $this->textContent : $this->subjectLine) . '</p>';

        return new Content(
            htmlString: $this->ensureLightEmailLayout($rawHtml),
        );
    }

    /**
     * Wrap raw email fragments in a clean, high-contrast, light-mode master layout (no dark background).
     */
    protected function ensureLightEmailLayout(string $html): string
    {
        if (stripos($html, '<html') !== false || stripos($html, '<body') !== false) {
            return $html;
        }

        $subjectEscaped = htmlspecialchars($this->subjectLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subjectEscaped}</title>
</head>
<body style="margin: 0; padding: 32px 16px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.06);">
        <!-- Top Brand Header Banner -->
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 24px 32px; text-align: left;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td>
                        <h1 style="margin: 0; font-size: 19px; font-weight: 800; color: #ffffff; letter-spacing: -0.3px;">EMK Center</h1>
                        <p style="margin: 3px 0 0 0; font-size: 12px; color: #fef3c7; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">American Spaces Bangladesh</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Email Body Content -->
        <div style="padding: 32px; color: #1e293b; font-size: 15px;">
            {$html}
        </div>

        <!-- Footer -->
        <div style="padding: 20px 32px; background-color: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; line-height: 1.5;">
            <p style="margin: 0;">This is an official communication from <strong>EMK Center</strong> (American Spaces Bangladesh).</p>
            <p style="margin: 4px 0 0 0;">House 3, Road 16, Dhanmondi, Dhaka 1209, Bangladesh &bull; <a href="https://emkcenter.org" style="color: #d97706; text-decoration: none; font-weight: 600;">emkcenter.org</a></p>
        </div>
    </div>
</body>
</html>
HTML;
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
