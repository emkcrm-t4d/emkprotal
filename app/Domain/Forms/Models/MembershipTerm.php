<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTerm extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'membership_terms';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'fee_bdt' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getWorkspaceId(): ?string
    {
        return $this->workspace_id ? (string) $this->workspace_id : null;
    }

    /**
     * Relationship: Members assigned to this term.
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'membership_term_id');
    }
}
