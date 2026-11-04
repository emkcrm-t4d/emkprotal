<?php

declare(strict_types=1);

namespace App\Domain\Forms\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

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
}
