<?php

declare(strict_types=1);

namespace App\Domain\Leads\Models;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model implements TenantEntityInterface
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'leads';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'has_parental_consent' => 'boolean',
            'has_us_experience' => 'boolean',
            'is_american_alumni' => 'boolean',
            'terms_agreed' => 'boolean',
            'submitted_at' => 'datetime',
            'age_at_submission' => 'integer',
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
     * Relationship: lead has many attachments (identity scan, photo, parental consent).
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeadAttachment::class, 'lead_id');
    }
}
