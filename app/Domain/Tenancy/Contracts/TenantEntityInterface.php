<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Contracts;

interface TenantEntityInterface
{
    /**
     * Get the workspace identifier for the tenant entity.
     */
    public function getWorkspaceId(): ?string;
}
