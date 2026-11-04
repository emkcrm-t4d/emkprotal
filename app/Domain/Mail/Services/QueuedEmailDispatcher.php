<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Models\EmailThread;
use App\Jobs\DispatchOfficialEmailJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueuedEmailDispatcher
{
    public function __construct(
        protected EmailTemplateRenderer $renderer
    ) {
    }

    /**
     * Build a deterministic deduplication key (max 191 chars) for a workspace,
     * template, recipient, and entity milestone identifier.
     */
    public function buildDeduplicationKey(
        string $workspaceId,
        string $templateKey,
        string $recipientEmail,
        string $entityKey
    ): string {
        $normalizedEmail = strtolower(trim($recipientEmail));
        $structured = "{$workspaceId}:{$templateKey}:{$normalizedEmail}:{$entityKey}";

        if (strlen($structured) <= 191) {
            return $structured;
        }

        return substr("{$workspaceId}:{$templateKey}:", 0, 120) . ':' . hash('sha256', $structured);
    }

    /**
     * Render a workspace EmailTemplate, persist/link the EmailThread and EmailMessage
     * with a deterministic deduplication_key, and queue DispatchOfficialEmailJob via Horizon.
     *
     * @param  array<string, scalar|null>  $variables
     */
    public function dispatchTemplate(
        string $workspaceId,
        string $templateKey,
        string $recipientEmail,
        array $variables = [],
        ?int $leadId = null,
        ?int $memberId = null,
        ?string $entityKey = null,
        string $locale = 'bilingual',
        ?int $actorId = null
    ): EmailMessage {
        $cleanRecipient = strtolower(trim($recipientEmail));
        $resolvedEntityKey = $entityKey ?? ($memberId !== null ? "member:{$memberId}" : ($leadId !== null ? "lead:{$leadId}" : 'general'));
        $deduplicationKey = $this->buildDeduplicationKey($workspaceId, $templateKey, $cleanRecipient, $resolvedEntityKey);

        // Idempotent deduplication check: if already queued or sent, return existing EmailMessage immediately
        $existingRow = DB::table('email_messages')
            ->where('deduplication_key', $deduplicationKey)
            ->first();

        if ($existingRow !== null && in_array((string) $existingRow->status, ['queued', 'sent'], true)) {
            $existingModel = EmailMessage::query()->where('id', $existingRow->id)->first();
            if ($existingModel !== null) {
                return $existingModel;
            }

            $hydrated = new EmailMessage();
            $hydrated->forceFill((array) $existingRow);
            $hydrated->exists = true;

            return $hydrated;
        }

        $template = $this->resolveTemplate($workspaceId, $templateKey);
        $rendered = $this->renderer->render($template, $variables, $locale);

        $thread = $this->resolveOrCreateThread(
            $workspaceId,
            $cleanRecipient,
            $rendered['subject'],
            $leadId,
            $memberId
        );

        $officialFrom = (string) config('mail.imap.official_address', config('mail.from.address', 'infodesk@emkcenter.org'));
        $messageIdHeader = '<crm-' . Str::uuid() . '@emkcenter.org>';

        if ($existingRow !== null) {
            // Re-queue previously failed message under the same deduplication_key
            DB::table('email_messages')
                ->where('id', $existingRow->id)
                ->update([
                    'status' => 'queued',
                    'subject' => $rendered['subject'],
                    'body_html' => $rendered['body_html'],
                    'body_text' => $rendered['body_text'],
                    'failure_reason' => null,
                    'sent_by_user_id' => $actorId,
                    'updated_at' => now(),
                ]);

            $messageId = (int) $existingRow->id;
        } else {
            $message = EmailMessage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'email_thread_id' => $thread->id,
                'lead_id' => $leadId,
                'member_id' => $memberId,
                'direction' => 'outbound',
                'status' => 'queued',
                'from_email' => $officialFrom,
                'to_email' => $cleanRecipient,
                'subject' => $rendered['subject'],
                'body_html' => $rendered['body_html'],
                'body_text' => $rendered['body_text'],
                'message_id_header' => $messageIdHeader,
                'template_key' => $templateKey,
                'milestone_type' => $template->milestone_type,
                'deduplication_key' => $deduplicationKey,
                'sent_by_user_id' => $actorId,
            ]);

            $thread->update([
                'lead_id' => $thread->lead_id ?? $leadId,
                'member_id' => $thread->member_id ?? $memberId,
                'last_message_at' => now(),
                'message_count' => (int) DB::table('email_messages')->where('email_thread_id', $thread->id)->count(),
            ]);

            $messageId = (int) $message->id;
        }

        DispatchOfficialEmailJob::dispatch($messageId);

        $reloaded = EmailMessage::query()->where('id', $messageId)->first();
        if ($reloaded !== null) {
            return $reloaded;
        }

        $row = DB::table('email_messages')->where('id', $messageId)->first();
        $hydrated = new EmailMessage();
        $hydrated->forceFill((array) $row);
        $hydrated->exists = true;

        return $hydrated;
    }

    /**
     * Redact any SMTP/IMAP password, secret, token, API key, or connection URI credential
     * so raw secrets never leak into email_messages.failure_reason or audit_logs per DEC-11 and T-08-07.
     */
    public function sanitizeFailureMessage(string $rawError): string
    {
        // Redact URI credentials such as smtp://user:secret@host or imaps://user:secret@host
        $sanitized = preg_replace('#(://[^:\s/@]+:)([^@\s]+)(@)#i', '$1[REDACTED]$3', $rawError) ?? $rawError;

        // Redact key=value or key: value patterns for sensitive credential tokens
        $sanitized = preg_replace(
            '/\b(password|passwd|pass|secret|token|api_key|apikey|authorization|auth|credential|credentials)\b(\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;]+)/i',
            '$1$2[REDACTED]',
            $sanitized
        ) ?? $sanitized;

        return Str::limit(trim($sanitized), 1000);
    }

    /**
     * Resolve an active EmailTemplate for the workspace and template_key,
     * falling back to canonical defaults if not yet seeded.
     */
    protected function resolveTemplate(string $workspaceId, string $templateKey): EmailTemplate
    {
        $row = DB::table('email_templates')
            ->where('workspace_id', $workspaceId)
            ->where('template_key', $templateKey)
            ->first();

        if ($row !== null) {
            $template = new EmailTemplate();
            $template->forceFill((array) $row);
            $template->allowed_variables = is_string($row->allowed_variables)
                ? (json_decode($row->allowed_variables, true) ?? [])
                : (is_array($row->allowed_variables) ? $row->allowed_variables : []);
            $template->is_active = (bool) $row->is_active;
            $template->exists = true;

            return $template;
        }

        // Create default template row if workspace exists
        $defaultSubjectEn = 'Notification from {{workspace_name}}';
        $defaultBodyEn = '<p>Dear {{applicant_name}}{{member_name}},</p><p>{{reason}}</p><p>Regards,<br>{{workspace_name}}</p>';
        $defaultSubjectBn = '{{workspace_name}} থেকে বিজ্ঞপ্তি';
        $defaultBodyBn = '<p>প্রিয় {{applicant_name}}{{member_name}},</p><p>{{reason}}</p><p>শুভেচ্ছান্তে,<br>{{workspace_name}}</p>';

        $template = EmailTemplate::create([
            'workspace_id' => $workspaceId,
            'template_key' => $templateKey,
            'name' => Str::headline($templateKey),
            'milestone_type' => $templateKey,
            'subject_en' => $defaultSubjectEn,
            'body_en' => $defaultBodyEn,
            'subject_bn' => $defaultSubjectBn,
            'body_bn' => $defaultBodyBn,
            'allowed_variables' => EmailTemplateRenderer::ALLOWED_PLACEHOLDERS,
            'is_active' => true,
        ]);

        return $template;
    }

    /**
     * Resolve or create an EmailThread in the workspace for the recipient.
     */
    protected function resolveOrCreateThread(
        string $workspaceId,
        string $recipientEmail,
        string $subject,
        ?int $leadId,
        ?int $memberId
    ): EmailThread {
        $query = DB::table('email_threads')
            ->where('workspace_id', $workspaceId)
            ->where('participant_email', $recipientEmail);

        if ($memberId !== null) {
            $existingRow = (clone $query)->where('member_id', $memberId)->orderByDesc('id')->first();
            if ($existingRow !== null) {
                $thread = new EmailThread();
                $thread->forceFill((array) $existingRow);
                $thread->exists = true;

                return $thread;
            }
        }

        if ($leadId !== null) {
            $existingRow = (clone $query)->where('lead_id', $leadId)->orderByDesc('id')->first();
            if ($existingRow !== null) {
                $thread = new EmailThread();
                $thread->forceFill((array) $existingRow);
                $thread->exists = true;

                return $thread;
            }
        }

        $existingRow = $query->orderByDesc('id')->first();
        if ($existingRow !== null) {
            $thread = new EmailThread();
            $thread->forceFill((array) $existingRow);
            $thread->exists = true;

            return $thread;
        }

        return EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'lead_id' => $leadId,
            'member_id' => $memberId,
            'participant_email' => $recipientEmail,
            'subject' => $subject !== '' ? $subject : 'EMK Center Official Notification',
            'last_message_at' => now(),
            'message_count' => 0,
            'is_resolved' => false,
        ]);
    }
}
