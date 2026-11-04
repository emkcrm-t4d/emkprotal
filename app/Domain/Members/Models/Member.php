<?php

declare(strict_types=1);

namespace App\Domain\Members\Models;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use App\Domain\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model implements TenantEntityInterface
{
    use BelongsToTenant;

    protected $table = 'members';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'start_date' => 'date',
            'expiry_date' => 'date',
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
     * Relationship: The originating lead applicant record.
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Relationship: The portal user credentials account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: The membership term defining validity duration.
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(MembershipTerm::class, 'membership_term_id');
    }

    /**
     * Relationship: Member billing and fee invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'member_id');
    }

    /**
     * Relationship: Member physical and digital ID cards.
     */
    public function idCards(): HasMany
    {
        return $this->hasMany(IdCard::class, 'member_id');
    }

    /**
     * Accessor: Formatted full member name.
     */
    public function getFullNameAttribute(): string
    {
        $middle = !empty($this->middle_name) ? $this->middle_name . ' ' : '';

        return trim($this->first_name . ' ' . $middle . $this->last_name);
    }
}
