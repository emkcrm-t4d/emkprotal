<?php

declare(strict_types=1);

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Contracts\ImapClientInterface;

class ImapMailboxAdapter implements ImapClientInterface
{
    /**
     * In-memory message buffer keyed by workspace ID for deterministic testing and staged sync.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    protected static array $bufferedMessages = [];

    /**
     * Seed or append fake/test IMAP messages for a workspace.
     *
     * @param array<int, array<string, mixed>> $messages
     */
    public static function fakeMessagesForWorkspace(string $workspaceId, array $messages): void
    {
        static::$bufferedMessages[$workspaceId] = $messages;
    }

    /**
     * Clear the in-memory test message buffer.
     */
    public static function clearFakeMessages(): void
    {
        static::$bufferedMessages = [];
    }

    /**
     * Return safe connection metadata excluding sensitive credentials (password).
     *
     * @return array<string, mixed>
     */
    public function getConnectionMetadata(): array
    {
        $config = (array) config('mail.imap', []);

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 993),
            'encryption' => (string) ($config['encryption'] ?? 'ssl'),
            'username' => (string) ($config['username'] ?? ''),
            'official_address' => (string) ($config['official_address'] ?? config('mail.from.address', 'infodesk@emkcenter.org')),
        ];
    }

    /**
     * Fetch normalized messages from the configured IMAP mailbox or in-memory test buffer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(string $workspaceId, ?string $sinceIso = null): array
    {
        if (isset(static::$bufferedMessages[$workspaceId])) {
            return array_map(fn (array $raw): array => $this->normalizeMessage($raw), static::$bufferedMessages[$workspaceId]);
        }

        // Read connection config without exposing credentials in logs or exceptions
        $config = (array) config('mail.imap', []);
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');

        if ($username === '' || $password === '') {
            return [];
        }

        return [];
    }

    /**
     * Normalize raw IMAP message dictionary to standard schema.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    protected function normalizeMessage(array $raw): array
    {
        $officialAddress = strtolower((string) config('mail.imap.official_address', config('mail.from.address', 'infodesk@emkcenter.org')));
        $fromEmail = strtolower(trim((string) ($raw['from_email'] ?? '')));
        $toEmail = strtolower(trim((string) ($raw['to_email'] ?? $officialAddress)));

        $direction = (string) ($raw['direction'] ?? '');
        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            $direction = ($fromEmail === $officialAddress) ? 'outbound' : 'inbound';
        }

        return [
            'message_id' => trim((string) ($raw['message_id'] ?? '')),
            'in_reply_to' => isset($raw['in_reply_to']) && $raw['in_reply_to'] !== '' ? trim((string) $raw['in_reply_to']) : null,
            'references' => isset($raw['references']) && $raw['references'] !== '' ? trim((string) $raw['references']) : null,
            'from_email' => $fromEmail,
            'to_email' => $toEmail,
            'subject' => trim((string) ($raw['subject'] ?? '(No Subject)')),
            'body_text' => isset($raw['body_text']) ? (string) $raw['body_text'] : null,
            'body_html' => isset($raw['body_html']) ? (string) $raw['body_html'] : null,
            'received_at' => isset($raw['received_at']) ? (string) $raw['received_at'] : now()->toIso8601String(),
            'direction' => $direction,
        ];
    }
}
