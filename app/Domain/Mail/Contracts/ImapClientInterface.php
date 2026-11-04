<?php

declare(strict_types=1);

namespace App\Domain\Mail\Contracts;

interface ImapClientInterface
{
    /**
     * Fetch normalized email messages for the specified workspace since an optional ISO timestamp.
     *
     * Each returned item must contain:
     * - message_id: string
     * - in_reply_to: ?string
     * - references: ?string
     * - from_email: string
     * - to_email: string
     * - subject: string
     * - body_text: ?string
     * - body_html: ?string
     * - received_at: ?string
     * - direction: 'inbound'|'outbound'
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchMessages(string $workspaceId, ?string $sinceIso = null): array;
}
