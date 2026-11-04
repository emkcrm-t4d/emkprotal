<?php

declare(strict_types=1);

namespace App\Domain\Leads\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAttachment extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'lead_attachments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'youth_protection' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Get the workspace identifier for the tenant entity.
     */
    public function getWorkspaceId(): ?string
    {
        return $this->workspace_id ?? $this->lead?->workspace_id;
    }

    /**
     * Relationship: attachment belongs to parent lead.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
