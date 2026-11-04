<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Mail\Contracts\ImapClientInterface;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailboxSyncService
{
    public function __construct(
        protected ImapClientInterface $imapClient
    ) {
    }

    /**
     * Synchronize the official Information Desk mailbox for the specified workspace.
     *
     * @return array{imported: int, skipped_duplicates: int, unmatched: int}
     */
    public function syncWorkspaceMailbox(string $workspaceId, ?string $sinceIso = null): array
    {
        $rawMessages = $this->imapClient->fetchMessages($workspaceId, $sinceIso);

        $imported = 0;
        $skippedDuplicates = 0;
        $unmatched = 0;

        $officialAddress = strtolower((string) config('mail.imap.official_address', config('mail.from.address', 'infodesk@emkcenter.org')));

        foreach ($rawMessages as $msg) {
            $messageId = trim((string) ($msg['message_id'] ?? ''));

            // Enforce idempotency on message_id_header within workspace
            if ($messageId !== '') {
                $exists = EmailMessage::where('workspace_id', $workspaceId)
                    ->where('message_id_header', $messageId)
                    ->exists();

                if ($exists) {
                    $skippedDuplicates++;
                    continue;
                }
            }

            $direction = (string) ($msg['direction'] ?? 'inbound');
            if (!in_array($direction, ['inbound', 'outbound'], true)) {
                $direction = 'inbound';
            }

            $fromEmail = strtolower(trim((string) ($msg['from_email'] ?? '')));
            $toEmail = strtolower(trim((string) ($msg['to_email'] ?? $officialAddress)));
            $counterpartyEmail = $direction === 'inbound' ? $fromEmail : $toEmail;

            if ($counterpartyEmail === '') {
                $unmatched++;
                continue;
            }

            // Match counterparty email within workspace: Member first, Lead second
            $member = Member::where('workspace_id', $workspaceId)
                ->where('email', $counterpartyEmail)
                ->first();

            $lead = null;
            if ($member !== null && $member->lead_id !== null) {
                $lead = Lead::where('workspace_id', $workspaceId)
                    ->where('id', $member->lead_id)
                    ->first();
            }

            if ($lead === null) {
                $lead = Lead::where('workspace_id', $workspaceId)
                    ->where('email', $counterpartyEmail)
                    ->latest('id')
                    ->first();
            }

            if ($member === null && $lead !== null && $lead->converted_member_id !== null) {
                $member = Member::where('workspace_id', $workspaceId)
                    ->where('id', $lead->converted_member_id)
                    ->first();
            }

            // Ignore unrelated external emails not belonging to any workspace Lead or Member
            if ($member === null && $lead === null) {
                $unmatched++;
                continue;
            }

            $leadId = $lead?->id ?? $member?->lead_id;
            $memberId = $member?->id;

            $subject = trim((string) ($msg['subject'] ?? '(No Subject)'));
            $inReplyTo = isset($msg['in_reply_to']) && $msg['in_reply_to'] !== '' ? trim((string) $msg['in_reply_to']) : null;
            $references = isset($msg['references']) && $msg['references'] !== '' ? trim((string) $msg['references']) : null;
            $bodyHtml = isset($msg['body_html']) ? $this->sanitizeHtml((string) $msg['body_html']) : null;
            $bodyText = isset($msg['body_text']) ? (string) $msg['body_text'] : ($bodyHtml !== null ? trim(strip_tags($bodyHtml)) : '');
            $receivedAt = isset($msg['received_at']) && $msg['received_at'] !== ''
                ? Carbon::parse((string) $msg['received_at'])
                : now();

            DB::transaction(function () use (
                $workspaceId,
                $leadId,
                $memberId,
                $counterpartyEmail,
                $subject,
                $messageId,
                $inReplyTo,
                $references,
                $direction,
                $fromEmail,
                $toEmail,
                $bodyHtml,
                $bodyText,
                $receivedAt
            ): void {
                $thread = $this->resolveOrCreateThread(
                    $workspaceId,
                    $counterpartyEmail,
                    $subject,
                    $messageId,
                    $inReplyTo,
                    $references,
                    $leadId,
                    $memberId
                );

                $emailMessage = EmailMessage::create([
                    'uuid' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'email_thread_id' => $thread->id,
                    'lead_id' => $leadId,
                    'member_id' => $memberId,
                    'direction' => $direction,
                    'status' => $direction === 'inbound' ? 'received' : 'sent',
                    'from_email' => $fromEmail,
                    'to_email' => $toEmail,
                    'subject' => $subject,
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'message_id_header' => $messageId !== '' ? $messageId : '<sync-' . Str::uuid() . '@emkcenter.org>',
                    'in_reply_to_header' => $inReplyTo,
                    'references_header' => $references,
                    'sent_at' => $direction === 'outbound' ? $receivedAt : null,
                    'received_at' => $direction === 'inbound' ? $receivedAt : null,
                ]);

                $thread->update([
                    'lead_id' => $thread->lead_id ?? $leadId,
                    'member_id' => $thread->member_id ?? $memberId,
                    'last_message_at' => $receivedAt,
                    'message_count' => $thread->messages()->count(),
                ]);

                AuditLog::create([
                    'workspace_id' => $workspaceId,
                    'actor_id' => null,
                    'actor_guard' => 'system',
                    'event_type' => AuditLog::EVENT_MAILBOX_MESSAGE_SYNCED,
                    'auditable_type' => EmailMessage::class,
                    'auditable_id' => $emailMessage->id,
                    'old_values' => null,
                    'new_values' => [
                        'email_thread_id' => $thread->id,
                        'direction' => $direction,
                        'counterparty_email' => $counterpartyEmail,
                        'message_id_header' => $emailMessage->message_id_header,
                        'lead_id' => $leadId,
                        'member_id' => $memberId,
                    ],
                    'reason' => 'Synchronized official Information Desk mailbox message.',
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                    'user_agent' => request()->userAgent() ?? 'System/Console',
                ]);
            });

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped_duplicates' => $skippedDuplicates,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * Dispatch an official staff reply and persist the outbound EmailMessage in the thread.
     */
    public function sendAndRecordReply(
        EmailThread $thread,
        User $sender,
        string $bodyText,
        ?string $subject = null
    ): EmailMessage {
        $isElevatedAdmin = method_exists($sender, 'hasRole') && $sender->hasRole(['superadmin', 'global_admin']);
        $isManager = method_exists($sender, 'hasRole') && $sender->hasRole(['workspace_manager']);

        if (!$isElevatedAdmin && (string) $sender->workspace_id !== (string) $thread->workspace_id) {
            App::abort(403, 'Unauthorized workspace access for email reply.');
        }

        $canCommunicate = $isElevatedAdmin
            || $isManager
            || $sender->can('leads.communicate')
            || $sender->can('members.edit');

        if (!$canCommunicate) {
            App::abort(403, 'Forbidden: leads.communicate or members.edit permission required.');
        }

        $officialFrom = (string) config('mail.imap.official_address', config('mail.from.address', 'infodesk@emkcenter.org'));
        $recipientEmail = (string) $thread->participant_email;

        $resolvedSubject = $subject !== null && trim($subject) !== ''
            ? trim($subject)
            : $this->ensureReplyPrefix((string) $thread->subject);

        $latestMessage = EmailMessage::where('workspace_id', $thread->workspace_id)
            ->where('email_thread_id', $thread->id)
            ->latest('id')
            ->first();

        $inReplyTo = $latestMessage?->message_id_header;
        $references = $latestMessage !== null
            ? trim(($latestMessage->references_header ? $latestMessage->references_header . ' ' : '') . (string) $latestMessage->message_id_header)
            : null;

        $generatedMessageId = '<crm-' . Str::uuid() . '@emkcenter.org>';

        Mail::raw($bodyText, function ($mailMessage) use ($recipientEmail, $officialFrom, $resolvedSubject): void {
            $mailMessage->to($recipientEmail)
                ->from($officialFrom, (string) config('mail.from.name', 'EMK Center Information Desk'))
                ->subject($resolvedSubject);
        });

        return DB::transaction(function () use (
            $thread,
            $sender,
            $officialFrom,
            $recipientEmail,
            $resolvedSubject,
            $bodyText,
            $generatedMessageId,
            $inReplyTo,
            $references
        ): EmailMessage {
            $now = now();

            $emailMessage = EmailMessage::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $thread->workspace_id,
                'email_thread_id' => $thread->id,
                'lead_id' => $thread->lead_id,
                'member_id' => $thread->member_id,
                'direction' => 'outbound',
                'status' => 'sent',
                'from_email' => $officialFrom,
                'to_email' => $recipientEmail,
                'subject' => $resolvedSubject,
                'body_html' => '<p>' . nl2br(e($bodyText)) . '</p>',
                'body_text' => $bodyText,
                'message_id_header' => $generatedMessageId,
                'in_reply_to_header' => $inReplyTo,
                'references_header' => $references !== '' ? $references : null,
                'sent_by_user_id' => $sender->id,
                'sent_at' => $now,
            ]);

            $thread->update([
                'last_message_at' => $now,
                'message_count' => $thread->messages()->count(),
                'thread_reference' => $thread->thread_reference ?? $generatedMessageId,
            ]);

            AuditLog::create([
                'workspace_id' => $thread->workspace_id,
                'actor_id' => $sender->id,
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_REPLY_SENT,
                'auditable_type' => EmailMessage::class,
                'auditable_id' => $emailMessage->id,
                'old_values' => null,
                'new_values' => [
                    'email_thread_id' => $thread->id,
                    'to_email' => $recipientEmail,
                    'subject' => $resolvedSubject,
                    'message_id_header' => $generatedMessageId,
                ],
                'reason' => 'Dispatched official Information Desk email reply.',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'System/Console',
            ]);

            return $emailMessage;
        });
    }

    /**
     * Resolve an existing EmailThread by RFC-5322 headers or normalized subject, or create a new one.
     */
    protected function resolveOrCreateThread(
        string $workspaceId,
        string $participantEmail,
        string $subject,
        string $messageId,
        ?string $inReplyTo,
        ?string $references,
        ?int $leadId,
        ?int $memberId
    ): EmailThread {
        $candidateMessageIds = [];
        if ($inReplyTo !== null && $inReplyTo !== '') {
            $candidateMessageIds[] = $inReplyTo;
        }

        if ($references !== null && $references !== '') {
            $parts = preg_split('/\s+/', $references) ?: [];
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if ($trimmed !== '') {
                    $candidateMessageIds[] = $trimmed;
                }
            }
        }

        $candidateMessageIds = array_values(array_unique($candidateMessageIds));

        if (!empty($candidateMessageIds)) {
            $parentMessage = EmailMessage::where('workspace_id', $workspaceId)
                ->whereIn('message_id_header', $candidateMessageIds)
                ->latest('id')
                ->first();

            if ($parentMessage !== null && $parentMessage->thread !== null) {
                return $parentMessage->thread;
            }
        }

        $normalizedSubject = $this->normalizeSubject($subject);

        $existingThreads = EmailThread::where('workspace_id', $workspaceId)
            ->where('participant_email', $participantEmail)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();

        foreach ($existingThreads as $candidateThread) {
            if (strtolower($this->normalizeSubject((string) $candidateThread->subject)) === strtolower($normalizedSubject)) {
                return $candidateThread;
            }
        }

        return EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'lead_id' => $leadId,
            'member_id' => $memberId,
            'participant_email' => $participantEmail,
            'subject' => $normalizedSubject !== '' ? $normalizedSubject : $subject,
            'thread_reference' => $messageId !== '' ? $messageId : null,
            'last_message_at' => now(),
            'message_count' => 0,
            'is_resolved' => false,
        ]);
    }

    /**
     * Strip Re:/Fwd: prefixes from email subject for thread correlation.
     */
    public function normalizeSubject(string $subject): string
    {
        $clean = trim($subject);
        while (preg_match('/^(re|fwd|fw)\s*:\s*(.*)$/i', $clean, $matches) === 1) {
            $clean = trim($matches[2]);
        }

        return $clean !== '' ? $clean : $subject;
    }

    /**
     * Ensure outbound reply subject starts with Re: unless already prefixed.
     */
    protected function ensureReplyPrefix(string $subject): string
    {
        $trimmed = trim($subject);
        if (preg_match('/^re\s*:/i', $trimmed) === 1) {
            return $trimmed;
        }

        return 'Re: ' . $trimmed;
    }

    /**
     * Strip dangerous HTML tags and inline event handler attributes per T-08-01.
     */
    public function sanitizeHtml(string $html): string
    {
        $sanitized = preg_replace('#<(script|iframe|object|embed|style|applet|meta|link)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $sanitized = preg_replace('#<(script|iframe|object|embed|meta|link)\b[^>]*/?>#is', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
        $sanitized = preg_replace('/javascript\s*:/i', 'blocked:', $sanitized) ?? $sanitized;

        return trim($sanitized);
    }
}
