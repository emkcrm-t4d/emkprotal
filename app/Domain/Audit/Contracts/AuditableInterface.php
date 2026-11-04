<?php

declare(strict_types=1);

namespace App\Domain\Audit\Contracts;

interface AuditableInterface
{
    /**
     * Get the workspace identifier for the audit record.
     */
    public function getAuditWorkspaceId(): ?string;

    /**
     * Get custom audit reason for the model mutation if available.
     */
    public function getAuditCustomReason(): ?string;

    /**
     * Get list of attributes excluded from audit logging.
     *
     * @return array<int, string>
     */
    public function getAuditExcludedAttributes(): array;
}
