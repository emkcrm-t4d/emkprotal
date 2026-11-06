<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Mailables\TemplatedLifecycleMail;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Services\QueuedEmailDispatcher;
use App\Domain\Mail\Services\SmtpTransportManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class DispatchOfficialEmailJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted per MAIL-04.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job per MAIL-04.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300;

    public function __construct(
        public int $emailMessageId
    ) {
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'email-msg-' . $this->emailMessageId;
    }

    /**
     * Execute the job.
     */
    public function handle(QueuedEmailDispatcher $dispatcher, ?SmtpTransportManager $transportManager = null): void
    {
        $transportManager = $transportManager ?? app(SmtpTransportManager::class);
        $message = null;
        $activeRelay = null;

        try {
            DB::transaction(function () use (&$message, &$activeRelay, $transportManager): void {
                $message = EmailMessage::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($this->emailMessageId);

                // Idempotent retry guard: skip if missing or already sent
                if ($message === null || $message->status === 'sent') {
                    return;
                }

                $activeRelay = $transportManager->getPrimaryRelay($message->workspace_id);
                if ($activeRelay) {
                    $transportManager->applyRelayToDefaultMailConfig($activeRelay);
                }

                $mailable = new TemplatedLifecycleMail(
                    subjectLine: (string) $message->subject,
                    htmlContent: (string) ($message->body_html ?? ''),
                    textContent: (string) ($message->body_text ?? ''),
                    messageIdHeader: (string) ($message->message_id_header ?? ''),
                    inReplyToHeader: $message->in_reply_to_header ? (string) $message->in_reply_to_header : null,
                );

                Mail::to((string) $message->to_email)->send($mailable);

                if ($activeRelay) {
                    $activeRelay->update([
                        'status' => 'connected',
                        'last_tested_at' => now(),
                        'last_error_message' => null,
                    ]);
                }

                $now = now();

                $message->update([
                    'status' => 'sent',
                    'sent_at' => $now,
                    'failure_reason' => null,
                ]);

                EmailThread::withoutGlobalScopes()
                    ->where('id', $message->email_thread_id)
                    ->update([
                        'last_message_at' => $now,
                    ]);

                AuditLog::create([
                    'workspace_id' => $message->workspace_id,
                    'actor_id' => $message->sent_by_user_id,
                    'actor_guard' => $message->sent_by_user_id ? 'web' : 'system',
                    'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_DISPATCHED,
                    'auditable_type' => EmailMessage::class,
                    'auditable_id' => $message->id,
                    'old_values' => null,
                    'new_values' => [
                        'to_email' => $message->to_email,
                        'template_key' => $message->template_key,
                        'deduplication_key' => $message->deduplication_key,
                        'status' => 'sent',
                    ],
                    'reason' => 'Dispatched queued official lifecycle email via Horizon.',
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'Horizon/QueueWorker',
                ]);
            });
        } catch (\Throwable $e) {
            $sanitized = $dispatcher->sanitizeFailureMessage($e->getMessage());

            if ($activeRelay) {
                $activeRelay->update([
                    'status' => 'error',
                    'last_tested_at' => now(),
                    'last_error_message' => $sanitized,
                ]);
            }

            $failedMessage = $message ?? EmailMessage::withoutGlobalScopes()->find($this->emailMessageId);

            if ($failedMessage !== null) {
                $failedMessage->update([
                    'status' => 'failed',
                    'failure_reason' => $sanitized,
                ]);

                AuditLog::create([
                    'workspace_id' => $failedMessage->workspace_id,
                    'actor_id' => $failedMessage->sent_by_user_id,
                    'actor_guard' => $failedMessage->sent_by_user_id ? 'web' : 'system',
                    'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_FAILED,
                    'auditable_type' => EmailMessage::class,
                    'auditable_id' => $failedMessage->id,
                    'old_values' => null,
                    'new_values' => [
                        'to_email' => $failedMessage->to_email,
                        'template_key' => $failedMessage->template_key,
                        'failure_reason' => $sanitized,
                    ],
                    'reason' => 'Official lifecycle email dispatch failed.',
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'Horizon/QueueWorker',
                ]);
            }

            throw $e;
        }
    }
}
