<?php

declare(strict_types=1);

namespace App\Domain\Audit\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Get the workspace identifier for the audit record.
     */
    public function getAuditWorkspaceId(): ?string
    {
        return $this->workspace_id ?? Auth::user()?->workspace_id;
    }

    /**
     * Get custom audit reason for the model mutation if available.
     */
    public function getAuditCustomReason(): ?string
    {
        if (isset($this->auditCustomReason)) {
            return (string) $this->auditCustomReason;
        }

        if (Request::has('reason')) {
            return (string) Request::input('reason');
        }

        return null;
    }

    /**
     * Get list of attributes excluded from audit logging.
     *
     * @return array<int, string>
     */
    public function getAuditExcludedAttributes(): array
    {
        return property_exists($this, 'auditExcluded') && is_array($this->auditExcluded)
            ? $this->auditExcluded
            : [];
    }
}
