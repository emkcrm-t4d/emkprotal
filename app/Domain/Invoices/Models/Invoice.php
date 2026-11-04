<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Models;

use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_bdt' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the workspace identifier for the tenant entity.
     */
    public function getWorkspaceId(): ?string
    {
        return $this->workspace_id ? (string) $this->workspace_id : null;
    }

    /**
     * Relationship: The member associated with this invoice.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Relationship: The originating lead if created during conversion.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
