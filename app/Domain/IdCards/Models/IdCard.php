<?php

declare(strict_types=1);

namespace App\Domain\IdCards\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdCard extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'id_cards';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * Relationship: The member owning this ID card credential.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}
