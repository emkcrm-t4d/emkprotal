<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Mailables\TemplatedLifecycleMail;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\SmtpRelay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class SmtpTransportManager
{
    /**
     * Retrieve all active relays for the workspace, falling back to global relays.
     *
     * @return Collection<int, SmtpRelay>
     */
    public function getActiveRelaysForWorkspace(?string $workspaceId): Collection
    {
        $relays = SmtpRelay::query()
            ->active()
            ->when(!empty($workspaceId), function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            })
            ->orderedByPriority()
            ->get();

        if ($relays->isEmpty() && !empty($workspaceId)) {
            // Fallback to global active relays
            $relays = SmtpRelay::query()
                ->active()
                ->whereNull('workspace_id')
                ->orderedByPriority()
                ->get();
        }

        return $relays;
    }

    /**
     * Get the primary (highest priority) active relay.
     */
    public function getPrimaryRelay(?string $workspaceId): ?SmtpRelay
    {
        return $this->getActiveRelaysForWorkspace($workspaceId)->first();
    }

    /**
     * Check if the workspace or platform has broken SMTP connections.
     */
    public function hasBrokenConnection(?string $workspaceId): bool
    {
        return SmtpRelay::query()
            ->active()
            ->where('status', 'error')
            ->when(!empty($workspaceId), function ($q) use ($workspaceId) {
                $q->where(function ($sub) use ($workspaceId) {
                    $sub->where('workspace_id', $workspaceId)
                        ->orWhereNull('workspace_id');
                });
            })
            ->exists();
    }

    /**
     * Retrieve the latest broken relay for alert display.
     */
    public function getLatestBrokenRelay(?string $workspaceId): ?SmtpRelay
    {
        return SmtpRelay::query()
            ->active()
            ->where('status', 'error')
            ->when(!empty($workspaceId), function ($q) use ($workspaceId) {
                $q->where(function ($sub) use ($workspaceId) {
                    $sub->where('workspace_id', $workspaceId)
                        ->orWhereNull('workspace_id');
                });
            })
            ->latest('updated_at')
            ->first();
    }

    /**
     * Create a configured Symfony EsmtpTransport instance from a relay model.
     */
    public function createSymfonyTransport(SmtpRelay $relay): EsmtpTransport
    {
        $isSsl = ($relay->encryption === 'ssl');
        $transport = new EsmtpTransport(
            $relay->host,
            (int) $relay->port,
            $isSsl
        );

        if ($relay->encryption === 'starttls') {
            $transport->setAutoTls(true);
        } elseif ($relay->encryption === 'none') {
            $transport->setAutoTls(false);
        }

        if (!empty($relay->username)) {
            $transport->setUsername($relay->username);
            $transport->setPassword((string) $relay->password);
        }

        return $transport;
    }

    /**
     * Test connection handshake and optionally transmit a styled test email.
     *
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public function testRelay(SmtpRelay $relay, ?string $testRecipient = null): array
    {
        $startTime = microtime(true);

        try {
            $transport = $this->createSymfonyTransport($relay);

            // Execute transport handshake (EHLO / STARTTLS / AUTH)
            $transport->start();

            if (!empty($testRecipient)) {
                $mailer = new SymfonyMailer($transport);

                $email = (new Email())
                    ->from(new Address($relay->sender_email, $relay->sender_name ?: 'EMK CRM Mailer'))
                    ->to($testRecipient)
                    ->subject('[SMTP Verification] Official Mail Gateway Test - ' . $relay->name)
                    ->html($this->renderTestEmailHtml($relay, $testRecipient));

                if (!empty($relay->reply_to)) {
                    $email->replyTo($relay->reply_to);
                }

                $mailer->send($email);
            }

            $transport->stop();

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            $relay->update([
                'status' => 'connected',
                'last_tested_at' => now(),
                'last_error_message' => null,
                'latency_ms' => $latencyMs,
            ]);

            $successMsg = "SMTP server '{$relay->name}' connected successfully ({$latencyMs}ms)";
            if (!empty($testRecipient)) {
                $successMsg .= " and test message sent to {$testRecipient}.";
            }

            return [
                'success' => true,
                'latency_ms' => $latencyMs,
                'message' => $successMsg,
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $errorMessage = $e->getMessage();

            $relay->update([
                'status' => 'error',
                'last_tested_at' => now(),
                'last_error_message' => $errorMessage,
                'latency_ms' => $latencyMs,
            ]);

            Log::error("SMTP Relay test failed for [{$relay->id}] {$relay->name}: {$errorMessage}");

            return [
                'success' => false,
                'latency_ms' => $latencyMs,
                'message' => "SMTP Connection Failed: {$errorMessage}",
            ];
        }
    }

    /**
     * Dispatch an official email message with automatic priority failover across active relays.
     *
     * @return array{success: bool, relay_id: ?int, error: ?string}
     */
    public function dispatchOfficialMessage(EmailMessage $message): array
    {
        $relays = $this->getActiveRelaysForWorkspace($message->workspace_id);

        if ($relays->isEmpty()) {
            // No custom relays configured: fallback to default Laravel Mail transport
            try {
                $mailable = new TemplatedLifecycleMail(
                    subjectLine: (string) $message->subject,
                    htmlContent: (string) ($message->body_html ?? ''),
                    textContent: (string) ($message->body_text ?? ''),
                    messageIdHeader: (string) ($message->message_id_header ?? ''),
                    inReplyToHeader: $message->in_reply_to_header ? (string) $message->in_reply_to_header : null,
                );

                Mail::to((string) $message->to_email)->send($mailable);

                return ['success' => true, 'relay_id' => null, 'error' => null];
            } catch (Throwable $e) {
                return ['success' => false, 'relay_id' => null, 'error' => $e->getMessage()];
            }
        }

        $lastError = null;

        // Iterate relays by priority (1 = highest) for automatic failover
        foreach ($relays as $relay) {
            try {
                $transport = $this->createSymfonyTransport($relay);
                $mailer = new SymfonyMailer($transport);

                $email = (new Email())
                    ->from(new Address($relay->sender_email, $relay->sender_name ?: 'EMK CRM Mailer'))
                    ->to((string) $message->to_email)
                    ->subject((string) $message->subject)
                    ->html((string) ($message->body_html ?? ''))
                    ->text((string) ($message->body_text ?? ''));

                if (!empty($message->message_id_header)) {
                    $email->getHeaders()->addIdHeader('Message-ID', (string) $message->message_id_header);
                }

                if (!empty($message->in_reply_to_header)) {
                    $email->getHeaders()->addTextHeader('In-Reply-To', (string) $message->in_reply_to_header);
                }

                if (!empty($relay->reply_to)) {
                    $email->replyTo($relay->reply_to);
                }

                $mailer->send($email);

                // Relay succeeded: mark connected
                $relay->update([
                    'status' => 'connected',
                    'last_tested_at' => now(),
                    'last_error_message' => null,
                ]);

                return ['success' => true, 'relay_id' => $relay->id, 'error' => null];
            } catch (Throwable $e) {
                $lastError = "Relay '{$relay->name}' ({$relay->host}:{$relay->port}) failed: " . $e->getMessage();
                Log::warning($lastError . " - attempting failover to next relay if available.");

                // Flag this relay as broken so admins see the alert
                $relay->update([
                    'status' => 'error',
                    'last_tested_at' => now(),
                    'last_error_message' => $e->getMessage(),
                ]);
            }
        }

        return ['success' => false, 'relay_id' => null, 'error' => $lastError];
    }

    /**
     * Render a premium HTML body for test verification emails.
     */
    protected function renderTestEmailHtml(SmtpRelay $relay, string $recipient): string
    {
        $timestamp = now()->toDateTimeString();
        $encryptionUpper = strtoupper($relay->encryption);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SMTP Gateway Verification</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #0b1120; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #f1f5f9;">
    <div style="max-width: 580px; margin: 0 auto; background: #1e293b; border: 1px solid rgba(255,255,255,0.12); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 24px 32px; text-align: left;">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">EMK Center & American Spaces CRM</h1>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #fef3c7; font-weight: 500;">Official Mail Gateway Handshake & Dispatch Test</p>
        </div>
        <div style="padding: 32px;">
            <div style="padding: 14px 18px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); border-radius: 12px; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #34d399;">Connection & Authentication Verified Successfully</p>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #a7f3d0;">Your SMTP email server configuration is fully operational and authenticated.</p>
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 8px 0; color: #94a3b8; width: 140px;">Server Name:</td>
                    <td style="padding: 8px 0; color: #ffffff; font-weight: 600;">{$relay->name}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #94a3b8;">Host & Port:</td>
                    <td style="padding: 8px 0; color: #ffffff; font-family: monospace;">{$relay->host}:{$relay->port} ({$encryptionUpper})</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #94a3b8;">Sender Identity:</td>
                    <td style="padding: 8px 0; color: #ffffff;">{$relay->sender_name} &lt;{$relay->sender_email}&gt;</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #94a3b8;">Recipient:</td>
                    <td style="padding: 8px 0; color: #ffffff;">{$recipient}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #94a3b8;">Timestamp:</td>
                    <td style="padding: 8px 0; color: #ffffff;">{$timestamp} BST</td>
                </tr>
            </table>

            <p style="font-size: 12px; color: #64748b; margin: 0; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 16px;">
                Automated diagnostic dispatch from EMK Center Platform Services. No response required.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
